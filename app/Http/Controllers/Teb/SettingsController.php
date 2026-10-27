<?php

namespace App\Http\Controllers\Teb;

use App\Models\Lookups\Department;
use App\Services\Teb\ApproverCandidates;

// TEB Admin settings (read only). Approvers are not named: anyone in the department whose masterlist
// job / position level matches a level may sign it. This page shows who that currently is.
class SettingsController extends TebController
{
    public function index()
    {
        $candidates = rescue(fn() => ApproverCandidates::byDepartment(), [], report: true);

        return $this->render('Settings/Index', [
            'fixed'            => config('teb.approval_departments'),
            'jobLevelIds'      => config('teb.approval_job_level_ids'),
            'jobLevelWords'    => config('teb.approval_job_levels'),
            'adminDepartments' => array_values(config('teb.admin_departments')),
            'departments'      => Department::orderBy('dep_name')->get()->map(fn($d) => [
                'id'         => (int) $d->id,
                'name'       => $d->dep_name,
                'candidates' => $candidates[(int) $d->id] ?? (object) [],
            ]),
        ]);
    }
}
