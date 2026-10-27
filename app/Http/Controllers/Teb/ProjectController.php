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
    public function __construct(protected TebWorkflow $workflow) {}

    // TEB register: every entry, filterable by category ("Database per Category"), status and search.
    public function index(Request $request)
    {
        $projects = $this->filtered($request)->with('affectedAreas')->latest()->paginate(25)->withQueryString()
            ->through(fn($p) => TebPresenter::row($p));

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

        $myTurn = $project->status === TebProject::STATUS_FOR_APPROVAL
            ? $project->approvals->first(fn($a) => $a->isTurn() && $a->canBeSignedBy($user))
            : null;

        return $this->render('Projects/Show', [
            'p'       => TebPresenter::detail($project),
            'isOwner' => (string) $project->owner_employid === (string) $user->id,
            'myTurn'  => $myTurn ? ['id' => $myTurn->id, 'label' => $myTurn->level === TebApproval::ANY_LEVEL ? $myTurn->department_group : "{$myTurn->department_group} {$myTurn->level_label}"] : null,
            'nextNo'  => $project->teb_no ? null : TebProject::nextTebNo(),
        ]);
    }

    public function edit(TebProject $project)
    {
        $this->authorizeOwner($project);
        abort_unless($project->isEditable(), 403, 'This TEB can no longer be edited.');

        return $this->render('Projects/Form', $this->formData($project->load('affectedAreas', 'attachments')));
    }

    public function update(Request $request, TebProject $project)
    {
        $this->authorizeOwner($project);
        abort_unless($project->isEditable(), 403, 'This TEB can no longer be edited.');

        $data = $this->validated($request, $project);

        DB::connection($project->getConnectionName())->transaction(function () use ($project, $data, $request) {
            $project->update($data);
            $this->syncAreas($project, $request->input('affected_areas', []));
            $this->storeUploads($project, $request);
        });

        return redirect()->route('teb.projects.show', $project)->with('success', 'Project updated.');
    }

    // "Generate System Generated TEB Form with Completed Status and upload the TEB Report".
    public function completeForm(TebProject $project)
    {
        $this->authorizeOwner($project);
        abort_unless($project->canBeCompleted(), 403, 'This TEB cannot be completed yet.');

        return $this->render('Projects/Complete', ['p' => TebPresenter::detail($project->load('affectedAreas', 'attachments'))]);
    }

    public function complete(Request $request, TebProject $project)
    {
        $user = TebUser::required();
        $this->authorizeOwner($project);

        $request->validate([
            'requires_4mcn'      => ['required', 'boolean'],
            'completion_remarks' => ['nullable', 'string', 'max:5000'],
            'teb_report'         => [$project->attachments()->where('type', 'teb_report')->exists() ? 'nullable' : 'required', 'file', 'max:' . config('teb.uploads.max_kb'), 'mimes:' . config('teb.uploads.mimes')],
        ]);

        $this->storeUploads($project, $request);
        $this->workflow->complete($project, $user, $request->boolean('requires_4mcn'), $request->input('completion_remarks'));

        return redirect()->route('teb.projects.show', $project)->with('success', 'TEB form completed and routed for approval.');
    }

    // Printable system-generated TEB form.
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
        $name = 'teb-report-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'TEB No',
                'Date Created',
                'Category',
                'TEB Change Title',
                'Customer',
                'Project Description',
                'Project Owner',
                'Owner Department',
                'Classification of Change',
                'Type of Change',
                'Affected Areas',
                'Status',
                'Current Status',
                'Change Description',
                '4MCN Required',
                '4MCN No',
                'Completed At',
                'Approved At'
            ]);

            foreach ($rows as $p) {
                fputcsv($out, [
                    $p->teb_no,
                    $p->created_at?->format('m/d/Y'),
                    $p->category_label,
                    $p->title,
                    $p->customer_name,
                    $p->project_description,
                    $p->owner_name,
                    $p->owner_department,
                    $p->classification_label,
                    $p->types_of_change_label,
                    $p->affected_areas_label,
                    $p->status_label,
                    $p->current_status,
                    $p->change_description,
                    $p->requires_4mcn === null ? '' : ($p->requires_4mcn ? 'Yes' : 'No'),
                    $p->fourMcn?->mcn_no,
                    $p->completed_at?->format('m/d/Y'),
                    $p->approved_at?->format('m/d/Y'),
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
            ->when($request->boolean('mine'), fn($q) => $q->where('owner_employid', $user->id))
            ->when($request->input('from'), fn($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->input('to'), fn($q, $d) => $q->whereDate('created_at', '<=', $d));
    }

    protected function validated(Request $request, ?TebProject $project = null): array
    {
        $hasFmea = $project && $project->attachments()->where('type', 'fmea')->exists();

        $data = $request->validate([
            'title'               => ['required', 'string', 'max:255'],
            'category'            => ['required', Rule::in(array_keys(config('teb.categories')))],
            'customer_id'         => ['nullable', 'alpha_num:ascii', 'max:50'],
            'classification'      => ['required', Rule::in(array_keys(config('teb.classifications')))],
            'types_of_change'     => ['required', 'array', 'min:1'],
            'types_of_change.*'   => [Rule::in(array_keys(config('teb.types_of_change')))],
            'affected_areas'      => ['required', 'array', 'min:1'],
            'affected_areas.*'    => ['integer'],
            'project_description' => ['required', 'string', 'max:10000'],
            'current_status'      => ['nullable', 'string', 'max:10000'],
            'change_description'  => ['nullable', 'string', 'max:10000'],
            // Slide 2: "If Major need FMEA document".
            'fmea'                => [$request->input('classification') === 'major' && ! $hasFmea ? 'required' : 'nullable', 'file', 'max:' . config('teb.uploads.max_kb'), 'mimes:' . config('teb.uploads.mimes')],
            'attachments.*'       => ['file', 'max:' . config('teb.uploads.max_kb'), 'mimes:' . config('teb.uploads.mimes')],
        ], [
            'fmea.required' => 'A Major change needs the FMEA document.',
        ]);

        $data['customer_id'] = $data['customer_id'] ?? null;
        $data['customer_name'] = $data['customer_id'] ? (Customer::find($data['customer_id'])?->display_name) : null;

        unset($data['affected_areas'], $data['fmea'], $data['attachments']);

        return $data;
    }

    protected function syncAreas(TebProject $project, array $ids): void
    {
        $project->affectedAreas()->delete();

        foreach (Department::whereIn('id', $ids)->get() as $d) {
            $project->affectedAreas()->create(['department_id' => $d->id, 'dep_name' => $d->dep_name]);
        }
    }

    // fmea / teb_report single fields, attachments[] multiple; $forceType for the generic upload box.
    protected function storeUploads(TebProject $project, Request $request, ?string $forceType = null): void
    {
        $disk  = config('teb.uploads.disk');
        $files = [];

        if ($forceType && $request->hasFile('file')) {
            $files[] = [$forceType, $request->file('file')];
        }
        foreach (['fmea' => 'fmea', 'teb_report' => 'teb_report'] as $field => $type) {
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
            'customers'   => collect(rescue(fn() => Customer::options(), []))->map(fn($name, $id) => ['id' => (string) $id, 'name' => $name])->values(),
            'departments' => Department::orderBy('dep_name')->get(['id', 'dep_name'])->map(fn($d) => ['id' => (int) $d->id, 'name' => $d->dep_name]),
            'ownerName'   => $project->owner_name ?: TebUser::required()->name,
            'ownerDept'   => $project->owner_department ?: TebUser::required()->dept,
            'today'       => now()->format('m/d/Y'),
        ];
    }

    protected function authorizeOwner(TebProject $project): void
    {
        abort_unless((string) $project->owner_employid === (string) TebUser::required()->id, 403, 'Only the originator can do this.');
    }
}
