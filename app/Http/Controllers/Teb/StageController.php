<?php
// app/Http/Controllers/Teb/StageController.php

namespace App\Http\Controllers\Teb;

use App\Models\Teb\TebAttachment;
use App\Models\Teb\TebProject;
use App\Services\Teb\TebUser;
use App\Services\Teb\TebWorkflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// TRB process steps 7 to 10 (and the trial plan decision when the TRB Admin approves plans):
// one decision per step, recorded by the TRB Admin. The originator may also report implementation done.
class StageController extends TebController
{
    public function __construct(protected TebWorkflow $workflow) {}

    public function __invoke(Request $request, TebProject $project)
    {
        $data = $request->validate([
            'action'          => ['required', Rule::in(array_keys(TebWorkflow::STAGE_ACTIONS))],
            'decision'        => ['nullable', 'boolean'],
            'remarks'         => ['nullable', 'string', 'max:2000'],
            'checks'          => ['array'],
            'checks.*'        => ['boolean'],
            'fields'          => ['array'],
            ...collect(['conditions', 'swi_spec_numbers', 'effective_date', 'affected_machines_lots', 'initial_results', 'verified_by', 'presentation_at', 'location'])
                ->mapWithKeys(fn($k) => ["fields.{$k}" => ['nullable', 'string', 'max:5000']])->all(),
            'fields.emails'   => ['nullable', 'array'],
            'fields.emails.*' => ['email'],
            'stage_file'      => ['nullable', 'file', 'max:' . config('teb.uploads.max_kb'), 'mimes:' . config('teb.uploads.mimes')],
            'stage_file_type' => ['nullable', Rule::in(array_keys(TebAttachment::TYPE_LABELS))],
        ]);

        $user    = TebUser::required();
        $isOwner = (string) $project->owner_employid === (string) $user->id;
        abort_unless(TebUser::isAdmin($user) || ($data['action'] === 'implemented' && $isOwner), 403, 'Only the TRB Admin can record this step.');

        if ($request->hasFile('stage_file')) {
            $project->attachments()->create([
                'type'          => $data['stage_file_type'] ?? TebAttachment::TYPE_OTHER,
                'original_name' => $request->file('stage_file')->getClientOriginalName(),
                'path'          => $request->file('stage_file')->store("teb/{$project->id}", config('teb.uploads.disk')),
                'size'          => $request->file('stage_file')->getSize(),
                'uploaded_by'   => $user->id,
            ]);
        }

        $this->workflow->stage(
            $project,
            $user,
            $data['action'],
            $request->has('decision') ? $request->boolean('decision') : null,
            $data['remarks'] ?? null,
            $data['checks'] ?? [],
            $data['fields'] ?? []
        );

        return redirect()->route('teb.projects.show', $project)->with('success', 'Status updated to ' . $project->status_label . '.');
    }
}
