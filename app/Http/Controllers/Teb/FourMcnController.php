<?php
// app/Http/Controllers/Teb/FourMcnController.php

namespace App\Http\Controllers\Teb;

use App\Models\Teb\TebProject;
use App\Services\Teb\TebPresenter;
use App\Services\Teb\TebUser;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// "4MCN?" decision -> "Generate System Generated 4MCN Form" (Originator).
class FourMcnController extends TebController
{
    public function create(TebProject $project)
    {
        $this->guard($project);

        return $this->render('FourMcn/Form', [
            'p'     => TebPresenter::detail($project->load('affectedAreas', 'attachments')),
            'today' => now()->toDateString(),
        ]);
    }

    public function store(Request $request, TebProject $project)
    {
        $this->guard($project);

        $data = $request->validate([
            'title'             => ['required', 'string', 'max:255'],
            'change_items'      => ['required', 'array', 'min:1'],
            'change_items.*'    => [Rule::in(array_keys(config('teb.4m_items')))],
            'classification'    => ['required', Rule::in(array_keys(config('teb.classifications')))],
            'types_of_change'   => ['required', 'array', 'min:1'],
            'types_of_change.*' => [Rule::in(array_keys(config('teb.types_of_change')))],
            'date_written'      => ['required', 'date'],
            'effectivity_date'  => ['required', 'date'],
            'expiration_date'   => ['nullable', 'date', 'after_or_equal:effectivity_date'],
            'extension_no'      => ['nullable', 'integer', 'between:1,3'],
            'before_change'     => ['required', 'string', 'max:10000'],
            'after_change'      => ['required', 'string', 'max:10000'],
            'reason'            => ['required', 'string', 'max:10000'],
            'attachments_note'  => ['nullable', 'string', 'max:5000'],
        ]);

        // Expiration date and extension only apply to temporary changes or waivers.
        if (! array_intersect($data['types_of_change'], ['temporary', 'waiver'])) {
            $data['expiration_date'] = null;
            $data['extension_no']    = null;
        }

        $user = TebUser::required();
        $project->fourMcn()->create($data + [
            'mcn_no'        => \App\Models\Teb\TebFourMcn::nextNo(),
            'prepared_by'   => $user->id,
            'prepared_name' => $user->name,
        ]);

        return redirect()->route('teb.fourmcn.show', $project)->with('success', '4MCN form generated.');
    }

    public function show(TebProject $project)
    {
        abort_unless($project->fourMcn, 404);

        return $this->render('FourMcn/Print', ['p' => TebPresenter::detail($project->load('affectedAreas', 'attachments', 'approvals', 'fourMcn'))]);
    }

    protected function guard(TebProject $project): void
    {
        abort_unless((string) $project->owner_employid === (string) TebUser::required()->id, 403, 'Only the originator can do this.');
        abort_unless($project->needsFourMcn(), 403, 'A 4MCN is not needed or was already generated.');
    }
}
