<?php
// app/Http/Controllers/Teb/DashboardController.php

namespace App\Http\Controllers\Teb;

use App\Models\Teb\TebApproval;
use App\Models\Teb\TebProject;
use App\Services\Teb\TebPresenter;
use App\Services\Teb\TebUser;

class DashboardController extends TebController
{
    // Slide 1 "Dashboard – Counter for Total TEB, then by Status".
    const COUNTER_STATUSES = [
        TebProject::STATUS_OPEN,
        TebProject::STATUS_HOLD,
        TebProject::STATUS_ONGOING,
        TebProject::STATUS_REVIEWED,
        TebProject::STATUS_SUBMITTED,
        TebProject::STATUS_PENDING_TEB_NUMBER,
        TebProject::STATUS_FOR_APPROVAL,
        TebProject::STATUS_RETURNED,
        TebProject::STATUS_APPROVED,
    ];

    public function __invoke()
    {
        $user = TebUser::required();

        $byStatus   = TebProject::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $byCategory = TebProject::selectRaw('category, status, count(*) as total')->groupBy('category', 'status')->get()
            ->groupBy('category');

        $myApprovals = TebApproval::with('project')
            ->signableBy($user)
            ->whereHas('project', fn($q) => $q->where('status', TebProject::STATUS_FOR_APPROVAL))
            ->get()
            ->filter->isTurn();

        return $this->render('Dashboard', [
            'total'       => $byStatus->sum(),
            'byStatus'    => $byStatus,
            'statuses'    => self::COUNTER_STATUSES,
            'byCategory'  => $byCategory->map(fn($rows) => $rows->pluck('total', 'status')),
            'pending4mcn' => TebProject::where('status', TebProject::STATUS_APPROVED)->where('requires_4mcn', true)->doesntHave('fourMcn')->count(),
            'myProjects'  => TebProject::where('owner_employid', $user->id)->latest()->limit(8)->get()->map(fn($p) => TebPresenter::row($p)),
            'myApprovals' => $myApprovals->map(fn($a) => [
                'id'               => $a->id,
                'project_id'       => $a->project->id,
                'display_no'       => $a->project->display_no,
                'title'            => $a->project->title,
                'department_group' => $a->department_group,
                'level_label'      => $a->level_label,
            ])->values(),
        ]);
    }
}
