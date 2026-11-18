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
    public function __construct(protected TebWorkflow $workflow)
    {
    }

    public function index(Request $request)
    {
        $user = TebUser::required();

        // Admins see every TRB in approval; approvers see the ones waiting on them.
        $projects = TebProject::with(['affectedAreas', 'approvals'])
            ->whereIn('status', [TebProject::STATUS_PLAN_FOR_APPROVAL, TebProject::STATUS_FOR_APPROVAL])
            ->when(! TebUser::isAdmin($user) || $request->boolean('mine'), fn ($q) => $q->whereHas('approvals', fn ($a) => $a->signableBy($user)
                ->whereRaw("round = (case when teb_projects.status = 'plan_for_approval' then 'plan' else 'results' end)")))
            ->orderBy('updated_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($p) => TebPresenter::row($p) + ['my_approval' => self::mine($p, $user)]);

        return $this->render('Approvals/Index', ['projects' => $projects, 'mine' => $request->boolean('mine')]);
    }

    // What this approver can do on a listed TRB: sign now, or wait for the boxes ahead (Approve only when they can sign).
    protected static function mine(TebProject $p, \App\Services\Teb\TebActor $user): ?array
    {
        $box = $p->approvalFor($user);
        if (! $box) {
            return null;
        }
        $waiting = $box->waitingOn();

        return ['round' => $box->round, 'label' => $box->sign_label, 'can_sign' => ! $waiting, 'waiting_on' => $waiting];
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
            $msg = 'Final approval recorded. Results validated and TSPI approved.';
        }
        if ($project->status === TebProject::STATUS_TRIAL_AUTHORIZED) {
            $msg = 'Final approval recorded. Trial plan approved; trial and validation authorized.';
        }

        return redirect()->route('teb.projects.show', $project)->with('success', $msg);
    }
}
