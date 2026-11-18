<?php
// app/Http/Controllers/Teb/ProjectController.php

namespace App\Http\Controllers\Teb;

use App\Models\Lookups\Department;
use App\Models\Qdn\Customer;
use App\Models\Teb\TebApproval;
use App\Models\Teb\TebAttachment;
use App\Models\Teb\TebProject;
use App\Services\Teb\TebPresenter;
use App\Services\Teb\TebUser;
use App\Services\Teb\TebWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProjectController extends TebController
{
    public function __construct(protected TebWorkflow $workflow)
    {
    }

    // TRB register: every entry, filterable by BMP driver, status and search.
    public function index(Request $request)
    {
        $projects = $this->filtered($request)->with('affectedAreas')->latest()->paginate(25)->withQueryString()
            ->through(fn ($p) => TebPresenter::row($p));

        return $this->render('Projects/Index', [
            'projects' => $projects,
            'mine'     => $request->boolean('mine'),
            'filters'  => $request->only('q', 'category', 'status', 'from', 'to'),
        ]);
    }

    public function create()
    {
        return $this->render('Projects/Form', $this->formData(new TebProject(['types_of_change' => []])));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $user = TebUser::required();

        $project = DB::connection((new TebProject)->getConnectionName())->transaction(function () use ($data, $request, $user) {
            $project = TebProject::create($data + [
                'owner_employid'   => $user->id,
                'owner_name'       => $user->name,
                'owner_department' => $user->dept,
                'status'           => TebProject::STATUS_SUBMITTED,
            ]);

            $this->syncAreas($project, $request->input('affected_areas', []));
            $this->storeUploads($project, $request);

            $project->statusLogs()->create([
                'to_status' => TebProject::STATUS_SUBMITTED,
                'remarks'   => 'Project registered',
                'employid'  => $user->id,
                'emp_name'  => $user->name,
            ]);

            return $project;
        });

        return redirect()->route('teb.projects.show', $project)->with('success', 'Project registered and submitted for review.');
    }

    public function show(TebProject $project)
    {
        $project->load(['affectedAreas', 'attachments', 'reviews', 'statusLogs', 'approvals', 'fourMcn']);
        $user = TebUser::required();

        $round  = $project->approvalRound();
        $myTurn = $round
            ? $project->approvals->where('round', $round)->first(fn ($a) => $a->isTurn() && $a->canBeSignedBy($user))
            : null;

        return $this->render('Projects/Show', [
            'p'       => TebPresenter::detail($project),
            'isOwner' => (string) $project->owner_employid === (string) $user->id,
            'myTurn'  => $myTurn ? ['id' => $myTurn->id, 'label' => $myTurn->level === TebApproval::ANY_LEVEL || $myTurn->isExecutive() ? $myTurn->department_group : "{$myTurn->department_group} {$myTurn->level_label}"] : null,
            'nextNo'  => $project->teb_no ? null : TebProject::nextTebNo(),
            'stage'   => [
                'planApproval' => config('teb.plan_approval'),
                'readiness'    => \App\Services\Teb\TebGates::READINESS_CHECKS,
                'piev'         => \App\Services\Teb\TebGates::PIEV_CHECKS,
                // ADI Oversight Team notice (step 7): addresses to pick from.
                'emails'       => $project->status === TebProject::STATUS_ADI_REVIEW && \App\Services\Teb\TebUser::isAdmin($user)
                    ? rescue(fn () => \App\Services\Teb\EmailDirectory::options(), [], report: true) : [],
            ],
        ]);
    }

    public function edit(TebProject $project)
    {
        $this->authorizeOwner($project);
        abort_unless($project->isEditable(), 403, 'This TRB can no longer be edited.');

        return $this->render('Projects/Form', $this->formData($project->load('affectedAreas', 'attachments')));
    }

    public function update(Request $request, TebProject $project)
    {
        $this->authorizeOwner($project);
        abort_unless($project->isEditable(), 403, 'This TRB can no longer be edited.');

        $data = $this->validated($request, $project);

        DB::connection($project->getConnectionName())->transaction(function () use ($project, $data, $request) {
            $project->update($data);
            $this->syncAreas($project, $request->input('affected_areas', []));
            $this->storeUploads($project, $request);
        });

        if ($project->status === TebProject::STATUS_REVISE) {
            $this->workflow->resubmitRevision($project, TebUser::required());

            return redirect()->route('teb.projects.show', $project)->with('success', 'Project revised and resubmitted for review.');
        }

        return redirect()->route('teb.projects.show', $project)->with('success', 'Project updated.');
    }

    // Step 4: the originator uploads the trial / qualification plan and submits it for approval.
    public function submitPlan(Request $request, TebProject $project)
    {
        $user = TebUser::required();
        $this->authorizeOwner($project);

        $file = ['nullable', 'file', 'max:'.config('teb.uploads.max_kb'), 'mimes:'.config('teb.uploads.mimes')];
        $request->validate([
            'trial_plan'            => [$project->attachments()->where('type', 'trial_plan')->exists() ? 'nullable' : 'required', ...array_slice($file, 1)],
            'control_plan'          => $file,
            'draft_swi'             => $file,
            'acceptance_criteria'   => ['required', 'string', 'max:5000'],
            'uses_customer_product' => ['required', 'boolean'],
            'remarks'               => ['nullable', 'string', 'max:2000'],
        ], ['trial_plan.required' => 'Upload the qualification / trial plan.']);

        $this->storeUploads($project, $request);
        $this->workflow->submitPlan($project, $user, $request->input('remarks'), [
            'acceptance_criteria'   => $request->input('acceptance_criteria'),
            'uses_customer_product' => $request->boolean('uses_customer_product'),
        ]);

        return redirect()->route('teb.projects.show', $project)->with('success', 'Trial plan submitted for approval.');
    }

    // "Generate System Generated TRB Form with Completed Status and upload the TRB Report".
    public function completeForm(TebProject $project)
    {
        $this->authorizeOwner($project);
        abort_unless($project->canBeCompleted(), 403, 'This TRB cannot be completed yet.');

        return $this->render('Projects/Complete', ['p' => TebPresenter::detail($project->load('affectedAreas', 'attachments'))]);
    }

    public function complete(Request $request, TebProject $project)
    {
        $user = TebUser::required();
        $this->authorizeOwner($project);

        $request->validate([
            'requires_4mcn'      => ['required', 'boolean'],
            'completion_remarks' => ['nullable', 'string', 'max:5000'],
            'teb_report'         => [$project->attachments()->where('type', 'teb_report')->exists() ? 'nullable' : 'required', 'file', 'max:'.config('teb.uploads.max_kb'), 'mimes:'.config('teb.uploads.mimes')],
            'customer_trial'     => ['nullable', 'file', 'max:'.config('teb.uploads.max_kb'), 'mimes:'.config('teb.uploads.mimes')],
            'residual_risks'     => ['nullable', 'string', 'max:5000'],
            'summary_of_changes' => ['nullable', 'string', 'max:5000'],
            'training_plan'      => ['nullable', 'string', 'max:5000'],
            'conclusion'         => ['required', 'string', 'max:5000'],
        ], ['conclusion.required' => 'Write the conclusion and recommendation.']);

        $this->storeUploads($project, $request);
        $this->workflow->complete($project, $user, $request->boolean('requires_4mcn'), $request->input('completion_remarks'),
            $request->only('residual_risks', 'summary_of_changes', 'training_plan', 'conclusion'));

        return redirect()->route('teb.projects.show', $project)->with('success', 'TRB form completed and routed for approval.');
    }

    // Printable system-generated TRB form.
    public function form(TebProject $project)
    {
        return $this->render('Projects/Print', ['p' => TebPresenter::detail($project->load('affectedAreas', 'attachments', 'approvals')), 'generatedAt' => now()->format('m/d/Y H:i')]);
    }

    public function upload(Request $request, TebProject $project)
    {
        $this->authorizeOwner($project);
        $request->validate(['type' => ['required', Rule::in(array_keys(TebAttachment::TYPE_LABELS))]]);

        $this->storeUploads($project, $request, $request->input('type'));

        return back()->with('success', 'File uploaded.');
    }

    public function download(TebProject $project, TebAttachment $attachment)
    {
        abort_unless($attachment->teb_project_id === $project->id, 404);

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk(config('teb.uploads.disk'));

        return $disk->download($attachment->path, $attachment->original_name);
    }

    // "Enable the user to extract report" — CSV opens directly in Excel.
    public function export(Request $request)
    {
        $rows = $this->filtered($request)->with('affectedAreas', 'fourMcn')->orderBy('created_at')->get();
        $name = 'teb-report-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['TRB No', 'Date Created', 'Category', 'TRB Change Title', 'Customer(s)', 'Project Description',
                'Project Owner', 'Owner Department', 'Classification of Change', 'Type of Change', 'Affected Areas',
                'Status', 'Current Status', 'Change Description', '4MCN Required', '4MCN No', 'Completed At', 'Approved At']);

            foreach ($rows as $p) {
                fputcsv($out, [
                    $p->teb_no, $p->created_at?->format('m/d/Y'), $p->category_label, $p->title, $p->customer_name,
                    $p->project_description, $p->owner_name, $p->owner_department, $p->classification_label,
                    $p->types_of_change_label, $p->affected_areas_label, $p->status_label, $p->current_status,
                    $p->change_description, $p->requires_4mcn === null ? '' : ($p->requires_4mcn ? 'Yes' : 'No'),
                    $p->fourMcn?->mcn_no, $p->completed_at?->format('m/d/Y'), $p->approved_at?->format('m/d/Y'),
                ]);
            }

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function filtered(Request $request)
    {
        $user = TebUser::required();

        return TebProject::query()
            ->category($request->input('category'))
            ->status($request->input('status'))
            ->search($request->input('q'))
            ->when($request->boolean('mine'), fn ($q) => $q->where('owner_employid', $user->id))
            ->when($request->input('from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->input('to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
    }

    protected function validated(Request $request, ?TebProject $project = null): array
    {
        $hasFmea = $project && $project->attachments()->where('type', 'fmea')->exists();

        // Classification follows the BMP categories picked (BMP maintenance on the Settings page).
        $raw = (array) $request->input('bmp', []);
        $bmp = array_filter($raw, fn ($v) => is_string($v) && $v !== '');
        $request->merge(['classification' => TebProject::classificationFor($bmp)]);
        // A driver checked without a category.
        $catalog = \App\Models\Teb\TebBmpCategory::map();
        $noCategory = array_diff_key(array_intersect_key($raw, $catalog), $bmp);
        if ($noCategory) {
            throw \Illuminate\Validation\ValidationException::withMessages(collect($noCategory)->mapWithKeys(fn ($v, $d) => ["bmp.{$d}" => 'Pick the '.config("teb.bmp_drivers.{$d}").' category.'])->all());
        }

        $data = $request->validate([
            'title'               => ['required', 'string', 'max:255'],
            'customer_ids'        => ['nullable', 'array'],
            'customer_ids.*'      => ['alpha_num:ascii', 'max:50', 'distinct'],
            'bmp'                 => ['required', 'array', 'min:1'],
            // Active categories; a retired one only stays allowed on an entry that already picked it.
            ...collect(\App\Models\Teb\TebBmpCategory::map(true))->mapWithKeys(fn ($cats, $d) => ["bmp.{$d}" => ['nullable',
                Rule::in(array_filter([...array_keys($cats), $project?->charter['bmp'][$d] ?? null]))]])->all(),
            'classification'      => ['required', Rule::in(array_keys(config('teb.classifications')))],
            'types_of_change'     => ['required', 'array', 'min:1'],
            'types_of_change.*'   => [Rule::in(array_keys(config('teb.types_of_change')))],
            'affected_areas'      => ['required', 'array', 'min:1'],
            'affected_areas.*'    => ['integer'],

            // Slide 2: "If Major need FMEA document".
            'fmea'                => [$request->input('classification') === 'major' && ! $hasFmea ? 'required' : 'nullable', 'file', 'max:'.config('teb.uploads.max_kb'), 'mimes:'.config('teb.uploads.mimes')],
            'charter'             => ['required', 'array'],
            ...collect(TebProject::CHARTER_FIELDS)->mapWithKeys(fn ($label, $k) => ["charter.{$k}" => [in_array($k, TebProject::CHARTER_REQUIRED, true) ? 'required' : 'nullable', 'string', 'max:5000']])->all(),

            'charter.member_ids'    => ['nullable', 'array'],
            'charter.member_ids.*'  => ['string', 'max:20', 'distinct'],
            'charter.sponsor_ids'   => ['nullable', 'array'],
            'charter.sponsor_ids.*' => ['string', 'max:20', 'distinct'],
            'charter.timeline_start' => ['nullable', 'date'],
            'charter.timeline_end'   => ['nullable', 'date', 'after_or_equal:charter.timeline_start'],
            'charter_file'        => ['nullable', 'file', 'max:'.config('teb.uploads.max_kb'), 'mimes:'.config('teb.uploads.mimes')],
            'attachments.*'       => ['file', 'max:'.config('teb.uploads.max_kb'), 'mimes:'.config('teb.uploads.mimes')],
        ], [
            'fmea.required' => 'A Major change needs the FMEA document.',
            'bmp.required' => 'Check at least one BMP driver and pick its category.',
            'classification.required' => 'Check at least one BMP driver and pick its category; the classification follows from it.',
            'charter.required' => 'Fill in the Project Charter.',
            ...collect(TebProject::CHARTER_REQUIRED)->mapWithKeys(fn ($k) => ["charter.{$k}.required" => 'The '.TebProject::CHARTER_FIELDS[$k].' is required.'])->all(),
        ]);
        $charter = array_intersect_key($data['charter'], TebProject::CHARTER_FIELDS)
            + ['bmp' => array_intersect_key($bmp, $catalog)];
        // Members / Sponsor(s): employees picked from the masterlist; their names are stored for the form and print.
        // An empty pick is not sent with the form, so a missing list means "none picked"; names typed in before
        // the pickers existed are kept until someone is picked.
        $old = $project?->charter ?? [];
        foreach (TebProject::CHARTER_PEOPLE as $field => $idsField) {
            $ids = array_values(array_map('strval', (array) ($data['charter'][$idsField] ?? [])));
            $charter[$idsField] = $ids;
            $charter[$field] = $ids
                ? implode(', ', \App\Services\Teb\EmployeeDirectory::names($ids))
                : (empty($old[$idsField]) ? ($old[$field] ?? null) : null);
        }
        // Timeline: start and target completion picked on a calendar, kept as dates and as the text shown on the form.
        $start = $data['charter']['timeline_start'] ?? null;
        $end = $data['charter']['timeline_end'] ?? null;
        if ($start || $end) {
            $fmt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('M j, Y') : '…';
            $charter['timeline_start'] = $start;
            $charter['timeline_end'] = $end;
            $charter['timeline'] = $fmt($start).' – '.$fmt($end);
        } else {
            $charter['timeline'] = $old['timeline'] ?? null;
        }
        $data['charter'] = $charter;
        // The register form no longer asks for a description: on a new entry the problem statement stands in for it
        // (lists, review invites, CSV export). Entries registered before keep theirs.
        if (! $project?->exists) {
            $data['project_description'] = $data['charter']['problem_statement'];
        }

        // Customers: several may be picked. Names are kept joined for lists, search, export and mails; customer_id is the first pick.
        $ids = array_values(array_map('strval', $data['customer_ids'] ?? []));
        $names = $ids ? Customer::whereIn((new Customer)->getKeyName(), $ids)->get()->mapWithKeys(fn ($c) => [(string) $c->getKey() => $c->display_name]) : collect();
        $data['customer_ids'] = $ids ?: null;
        $data['customer_id'] = $ids[0] ?? null;
        $data['customer_name'] = collect($ids)->map(fn ($id) => $names[$id] ?? null)->filter()->implode(', ') ?: null;

        unset($data['affected_areas'], $data['fmea'], $data['charter_file'], $data['attachments'], $data['bmp']);

        return $data;
    }

    protected function syncAreas(TebProject $project, array $ids): void
    {
        $project->affectedAreas()->delete();

        foreach (Department::whereIn('id', $ids)->get() as $d) {
            $project->affectedAreas()->create(['department_id' => $d->id, 'dep_name' => $d->dep_name]);
        }
    }

    // charter_file / fmea / teb_report / trial_plan / control_plan / draft_swi / customer_trial single fields, attachments[] multiple; $forceType for the generic upload box.
    protected function storeUploads(TebProject $project, Request $request, ?string $forceType = null): void
    {
        $disk  = config('teb.uploads.disk');
        $files = [];

        if ($forceType && $request->hasFile('file')) {
            $files[] = [$forceType, $request->file('file')];
        }
        foreach (['charter_file' => 'charter', 'fmea' => 'fmea', 'teb_report' => 'teb_report', 'trial_plan' => 'trial_plan',
            'control_plan' => 'control_plan', 'draft_swi' => 'draft_swi', 'customer_trial' => 'customer_trial'] as $field => $type) {
            if ($request->hasFile($field)) {
                $files[] = [$type, $request->file($field)];
            }
        }
        foreach ((array) $request->file('attachments', []) as $file) {
            $files[] = ['other', $file];
        }

        foreach ($files as [$type, $file]) {
            $project->attachments()->create([
                'type'          => $type,
                'original_name' => $file->getClientOriginalName(),
                'path'          => $file->store("teb/{$project->id}", $disk),
                'size'          => $file->getSize(),
                'uploaded_by'   => TebUser::current()?->id,
            ]);
        }
    }

    protected function formData(TebProject $project): array
    {
        return [
            'p'           => $project->exists ? TebPresenter::detail($project) : null,
            'customers'   => collect(rescue(fn () => Customer::options(), []))->map(fn ($name, $id) => ['id' => (string) $id, 'name' => $name])->values(),
            'departments' => Department::orderBy('dep_name')->get(['id', 'dep_name'])->map(fn ($d) => ['id' => (int) $d->id, 'name' => $d->dep_name]),
            'ownerName'   => $project->owner_name ?: TebUser::required()->name,
            'employees'   => rescue(fn () => \App\Services\Teb\EmployeeDirectory::options(), [], report: true),
            'ownerDept'   => $project->owner_department ?: TebUser::required()->dept,
            'today'       => now()->format('m/d/Y'),
        ];
    }

    protected function authorizeOwner(TebProject $project): void
    {
        abort_unless((string) $project->owner_employid === (string) TebUser::required()->id, 403, 'Only the originator can do this.');
    }
}
