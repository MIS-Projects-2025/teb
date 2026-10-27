<?php
// app/Services/Teb/ApproverCandidates.php

namespace App\Services\Teb;

use App\Models\Lookups\Department;
use Illuminate\Support\Facades\DB;

// Who can be picked as Supervisor / Section Head / Manager for each department, read from the masterlist:
// employee_department (department) + employee_job_level -> job_level and employee_position_level -> position_level
// (level), active employees only. A person matches a level by job_level id (config approval_job_level_ids),
// or if their job level or position level name contains one of the level's words.
class ApproverCandidates
{
    // [department_id => ['supervisor' => [...], 'section_head' => [...], 'manager' => [...], 'all' => [...]]]
    // Each entry: ['id' => employid, 'name' => 'Last, First', 'job_level' => name].
    public static function byDepartment(): array
    {
        return once(fn() => static::load());
    }

    // Employee ids who may sign this department + level.
    public static function eligible(?int $departmentId, string $level): array
    {
        return $departmentId ? array_column(static::byDepartment()[$departmentId][$level] ?? [], 'id') : [];
    }

    public static function canSign(string $employid, ?int $departmentId, string $level): bool
    {
        return in_array($employid, static::eligible($departmentId, $level), true);
    }

    // [[department_id, level], ...] this employee may sign.
    public static function signableBy(string $employid): array
    {
        $pairs = [];
        foreach (static::byDepartment() as $dept => $levels) {
            foreach (array_keys(config('teb.approval_levels')) as $level) {
                if (in_array($employid, array_column($levels[$level] ?? [], 'id'), true)) {
                    $pairs[] = [$dept, $level];
                }
            }
        }

        return $pairs;
    }

    protected static function load(): array
    {
        $rows = DB::connection('masterlist')->table('employee_department as ed')
            ->join('employee_details as e', 'e.employid', '=', 'ed.employid')
            ->leftJoin('employee_job_level as ejl', 'ejl.employid', '=', 'e.employid')
            ->leftJoin('job_level as jl', 'jl.id', '=', 'ejl.job_level_id')
            ->leftJoin('employee_position_level as epl', 'epl.employid', '=', 'e.employid')
            ->leftJoin('position_level as pl', 'pl.id', '=', 'epl.position_level_id')
            ->where('e.accstatus', 1)
            ->orderBy('e.lastname')->orderBy('e.firstname')
            ->get(['ed.department_id', 'e.employid', 'e.firstname', 'e.lastname', 'ejl.job_level_id', 'jl.emp_job_level_name', 'pl.position_level_name']);

        $levels = config('teb.approval_job_levels');
        $levelIds = config('teb.approval_job_level_ids', []);
        $out = [];
        foreach ($rows as $r) {
            $person = [
                'id'        => (string) $r->employid,
                'name'      => trim("{$r->lastname}, {$r->firstname}", ', '),
                'job_level' => implode(' · ', array_unique(array_filter([trim((string) $r->emp_job_level_name), trim((string) $r->position_level_name)]))),
            ];
            $dept = (int) $r->department_id;
            // An employee with several job/position rows appears once per row; merge their labels.
            if (isset($out[$dept]['all'][$person['id']])) {
                $merged = $out[$dept]['all'][$person['id']]['job_level'] . ' · ' . $person['job_level'];
                $person['job_level'] = implode(' · ', array_unique(array_filter(explode(' · ', $merged))));
            }
            $out[$dept]['all'][$person['id']] = $person;
            foreach ($levels as $level => $_) {
                if (isset($out[$dept][$level][$person['id']])) {
                    $out[$dept][$level][$person['id']] = $person;
                }
            }

            foreach ($levelIds as $level => $ids) {
                if ($r->job_level_id !== null && in_array((int) $r->job_level_id, $ids, true)) {
                    $out[$dept][$level][$person['id']] = $person;
                }
            }

            $jobLevel = mb_strtolower($person['job_level']);
            foreach ($levels as $level => $words) {
                foreach ($words as $word) {
                    if ($jobLevel !== '' && str_contains($jobLevel, mb_strtolower($word))) {
                        $out[$dept][$level][$person['id']] = $person;
                        break;
                    }
                }
            }
        }

        return array_map(fn($d) => array_map('array_values', $d), static::shareAcross($out));
    }

    // config teb.approval_shared_departments: each department in a group gets the signers of the whole group.
    protected static function shareAcross(array $out): array
    {
        $groups = config('teb.approval_shared_departments', []);
        if (! $groups) {
            return $out;
        }

        $ids = Department::query()->get(['id', 'dep_name'])
            ->mapToGroups(fn($d) => [mb_strtolower(trim($d->dep_name)) => (int) $d->id]);

        foreach ($groups as $names) {
            $deptIds = collect($names)->flatMap(fn($n) => $ids[mb_strtolower($n)] ?? [])->unique()->all();
            $merged = [];
            foreach ($deptIds as $id) {
                foreach (array_intersect_key($out[$id] ?? [], array_flip(config('teb.approval_shared_levels', ['manager']))) as $level => $people) {
                    $merged[$level] = ($merged[$level] ?? []) + $people;
                }
            }
            foreach ($deptIds as $id) {
                foreach ($merged as $level => $people) {
                    $out[$id][$level] = ($out[$id][$level] ?? []) + $people;
                }
            }
        }

        return $out;
    }
}
