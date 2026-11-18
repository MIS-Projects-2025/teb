<?php
// app/Http/Controllers/Teb/DashboardController.php

namespace App\Http\Controllers\Teb;

use App\Models\Teb\TebApproval;
use App\Models\Teb\TebProject;
use App\Services\Teb\TebPresenter;
use App\Services\Teb\TebUser;

class DashboardController extends TebController
{
    // Slide 1 "Dashboard – Counter for Total TRB, then by Status".
    // In process order (A. Initiate & Plan, B. Validate & Approve, C. Implement & Close).
    const COUNTER_STATUSES = [
        TebProject::STATUS_SUBMITTED,
        TebProject::STATUS_OPEN,
        TebProject::STATUS_HOLD,
        TebProject::STATUS_ONGOING,
        TebProject::STATUS_REVISE,
        TebProject::STATUS_PENDING_TEB_NUMBER,
        TebProject::STATUS_REVIEWED,
        TebProject::STATUS_PLAN_FOR_APPROVAL,
        TebProject::STATUS_PLAN_RETURNED,
        TebProject::STATUS_TRIAL_AUTHORIZED,
        TebProject::STATUS_FOR_APPROVAL,
        TebProject::STATUS_RETURNED,
        TebProject::STATUS_APPROVED,
        TebProject::STATUS_ADI_REVIEW,
        TebProject::STATUS_CUSTOMER_HOLD,
        TebProject::STATUS_DOC_RELEASE,
        TebProject::STATUS_READINESS_GAPS,
        TebProject::STATUS_IMPLEMENTATION,
        TebProject::STATUS_EFFECTIVENESS,
        TebProject::STATUS_CONTAINMENT,
        TebProject::STATUS_CLOSED,
        TebProject::STATUS_DROPPED,
    ];

    public function __invoke()
    {
        $user = TebUser::required();

        $byStatus   = TebProject::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        // By BMP driver: an entry counts under every driver it checked, split by classification.
        $byDriver = collect(config('teb.bmp_drivers'))->map(fn ($label, $d) => TebProject::whereNotNull("charter->bmp->{$d}")
            ->selectRaw('classification, count(*) as total')->groupBy('classification')->pluck('total', 'classification'))
            ->map(fn ($rows, $d) => ['key' => $d, 'label' => config("teb.bmp_drivers.{$d}"), 'total' => (int) $rows->sum(),
                'major' => (int) ($rows['major'] ?? 0), 'minor' => (int) ($rows['minor'] ?? 0)])->values();

        $count = fn (callable $pick) => (int) collect(TebProject::STATUS_STEPS)->filter(fn ($step, $s) => $pick($step, $s))
            ->keys()->sum(fn ($s) => $byStatus[$s] ?? 0);
        $ended = [TebProject::STATUS_CLOSED, TebProject::STATUS_DROPPED];

        $myApprovals = TebApproval::with('project')
            ->signableBy($user)
            ->whereHas('project', fn ($q) => $q->whereIn('status', [TebProject::STATUS_PLAN_FOR_APPROVAL, TebProject::STATUS_FOR_APPROVAL]))
            ->get()
            ->filter(fn ($a) => $a->round === $a->project->approvalRound() && $a->isTurn());

        return $this->render('Dashboard', [
            'total'       => $byStatus->sum(),
            'byStatus'    => $byStatus,
            'statuses'    => self::COUNTER_STATUSES,
            // The counters grouped by process phase (steps 1–4, 5–6, 7–10) and the ended ones.
            'pipeline'    => collect([
                'A. Initiate & Plan'    => fn ($step, $s) => $step <= 4 && ! in_array($s, $ended, true),
                'B. Validate & Approve' => fn ($step) => $step >= 5 && $step <= 6,
                'C. Implement & Close'  => fn ($step) => $step >= 7 && $step <= 10,
                'Ended'                 => fn ($step, $s) => in_array($s, $ended, true),
            ])->map(fn ($pick, $label) => ['label' => $label, 'statuses' => array_values(array_filter(self::COUNTER_STATUSES,
                fn ($s) => $pick(TebProject::STATUS_STEPS[$s] ?? 0, $s)))])->values(),
            'byDriver'    => $byDriver,
            'kpis'        => [
                'active'         => $count(fn ($step, $s) => ! in_array($s, $ended, true)),
                'approval'       => (int) (($byStatus[TebProject::STATUS_PLAN_FOR_APPROVAL] ?? 0) + ($byStatus[TebProject::STATUS_FOR_APPROVAL] ?? 0)),
                'implementation' => $count(fn ($step) => $step >= 7 && $step <= 10),
                'closed'         => (int) ($byStatus[TebProject::STATUS_CLOSED] ?? 0),
            ],
            'pending4mcn' => TebProject::whereIn('status', array_keys(array_filter(TebProject::STATUS_STEPS, fn ($step) => $step >= 7 && $step <= 10)))
                ->where('requires_4mcn', true)->doesntHave('fourMcn')->count(),
            'myProjects'  => TebProject::where('owner_employid', $user->id)->latest()->limit(6)->get()->map(fn ($p) => TebPresenter::row($p)),
            'myApprovals' => $myApprovals->map(fn ($a) => [
                'id'               => $a->id,
                'project_id'       => $a->project->id,
                'display_no'       => $a->project->display_no,
                'title'            => $a->project->title,
                'department_group' => $a->department_group,
                'level_label'      => $a->level_label,
                'round_label'      => \App\Models\Teb\TebApproval::ROUND_LABELS[$a->round] ?? '',
            ])->values(),
        ]);
    }
}
