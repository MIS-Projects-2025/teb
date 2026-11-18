<?php
// app/Http/Controllers/Teb/SettingsController.php

namespace App\Http\Controllers\Teb;

use App\Models\Lookups\Department;
use App\Services\Teb\ApproverCandidates;

// TRB Admin settings. BMP maintenance is edited here (BmpController); the rest is read only. Approvers are not named here: anyone in the department whose masterlist
// job / position level matches a level may sign it. This page shows who that currently is.
class SettingsController extends TebController
{
    public function index()
    {
        $candidates = rescue(fn () => ApproverCandidates::byDepartment(), [], report: true);

        $executives = rescue(fn () => ApproverCandidates::executives(), [], report: true);

        return $this->render('Settings/Index', [
            'bmp'              => rescue(fn () => \App\Models\Teb\TebBmpCategory::orderBy('sort')->orderBy('id')->get()
                ->map(fn ($c) => ['id' => $c->id, 'driver' => $c->driver, 'name' => $c->name, 'classification' => $c->classification,
                    'active' => $c->active, 'used' => $c->projectsQuery()->count()]), [], report: true),
            'executives'       => collect(config('teb.executive_approvals', []))->map(fn ($role, $key) => [
                'label'      => $role['label'],
                'candidates' => $executives[$key] ?? [],
            ])->values(),
            'fixed'            => config('teb.approval_departments'),
            'jobLevelIds'      => config('teb.approval_job_level_ids'),
            'jobLevelWords'    => config('teb.approval_job_levels'),
            'adminDepartments' => array_values(config('teb.admin_departments')),
            'departments'      => Department::orderBy('dep_name')->get()->map(fn ($d) => [
                'id'         => (int) $d->id,
                'name'       => $d->dep_name,
                'candidates' => $candidates[(int) $d->id] ?? (object) [],
            ]),
        ]);
    }
}
