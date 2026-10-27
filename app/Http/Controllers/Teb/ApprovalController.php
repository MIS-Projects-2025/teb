<?php
// app/Http/Controllers/Teb/ApprovalController.php

namespace App\Http\Controllers\Teb;

use App\Models\Teb\TebApproval;
use App\Models\Teb\TebProject;
use App\Services\Teb\TebPresenter;
use App\Services\Teb\TebUser;
use App\Services\Teb\TebWorkflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// "For approval" list and the approval section (slide 4).
class ApprovalController extends TebController
{
    public function __construct(protected TebWorkflow $workflow) {}

    public function index(Request $request)
    {
        $user = TebUser::required();

        // Admins see every TEB in approval; approvers see the ones waiting on them.
        $projects = TebProject::with(['affectedAreas', 'approvals'])
            ->where('status', TebProject::STATUS_FOR_APPROVAL)
            ->when(! TebUser::isAdmin($user) || $request->boolean('mine'), fn($q) => $q->whereHas('approvals', fn($a) => $a->signableBy($user)))
            ->orderBy('completed_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn($p) => TebPresenter::row($p));

        return $this->render('Approvals/Index', ['projects' => $projects, 'mine' => $request->boolean('mine')]);
    }

    public function act(Request $request, TebApproval $approval)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in([TebApproval::APPROVED, TebApproval::REJECTED])],
            'remarks'  => ['nullable', 'string', 'max:2000'],
        ]);

        $project = $this->workflow->act($approval, TebUser::required(), $data['decision'], $data['remarks'] ?? null);

        $msg = $data['decision'] === TebApproval::APPROVED ? 'Approved.' : 'Disapproved and returned to the originator.';
        if ($project->status === TebProject::STATUS_APPROVED) {
            $msg = 'Final approval recorded. TEB is approved.';
        }

        return redirect()->route('teb.projects.show', $project)->with('success', $msg);
    }
}
