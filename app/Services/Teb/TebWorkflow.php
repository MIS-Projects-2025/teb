<?php
// app/Services/Teb/TebWorkflow.php

namespace App\Services\Teb;

use App\Models\Lookups\Department;
use App\Models\Masterlist\Employee as MasterEmployee;
use App\Models\Teb\TebApproval;
use App\Models\Teb\TebProject;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// All status changes go through here so every step lands in teb_status_logs.
class TebWorkflow
{
    public function changeStatus(TebProject $project, string $to, ?TebActor $by, ?string $remarks = null): TebProject
    {
        $from = $project->status;
        $project->status = $to;
        $project->save();

        $project->statusLogs()->create([
            'from_status' => $from,
            'to_status'   => $to,
            'remarks'     => $remarks,
            'employid'    => $by?->id,
            'emp_name'    => $by?->name,
        ]);

        return $project;
    }

    // Slide 3 "Update status". Reviewed without a TEB No. waits on "Assign TEB Number".
    public function updateReviewStatus(TebProject $project, string $to, TebActor $by, ?string $remarks): TebProject
    {
        if (! in_array($to, TebProject::REVIEW_STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Invalid review status.']);
        }

        if (in_array($project->status, [TebProject::STATUS_FOR_APPROVAL, TebProject::STATUS_APPROVED], true)) {
            throw ValidationException::withMessages(['status' => 'This TEB is already in approval.']);
        }

        if ($to === TebProject::STATUS_REVIEWED && ($reason = $project->reviewBlockReason())) {
            throw ValidationException::withMessages(['status' => $reason]);
        }

        if ($to === TebProject::STATUS_REVIEWED && ! $project->teb_no) {
            $to = TebProject::STATUS_PENDING_TEB_NUMBER;
        }

        return $this->changeStatus($project, $to, $by, $remarks);
    }

    // TEB No. is always system generated for now.
    public function assignTebNo(TebProject $project, TebActor $by): TebProject
    {
        if ($project->teb_no) {
            throw ValidationException::withMessages(['teb_no' => 'A TEB No. is already assigned.']);
        }

        return DB::connection($project->getConnectionName())->transaction(function () use ($project, $by) {
            $project->teb_no = TebProject::nextTebNo();
            $project->teb_no_assigned_at = now();
            $project->save();

            $next = $project->status === TebProject::STATUS_PENDING_TEB_NUMBER
                ? TebProject::STATUS_REVIEWED
                : $project->status;

            return $this->changeStatus($project, $next, $by, "TEB No. {$project->teb_no} assigned");
        });
    }

    // Originator generates the completed TEB form; the portal routes it for approval.
    public function complete(TebProject $project, TebActor $by, bool $requires4mcn, ?string $remarks): TebProject
    {
        if (! $project->canBeCompleted()) {
            throw ValidationException::withMessages(['status' => 'This TEB cannot be completed yet.']);
        }

        if (! $project->attachments()->where('type', 'teb_report')->exists()) {
            throw ValidationException::withMessages(['teb_report' => 'Upload the TEB Report first.']);
        }

        return DB::connection($project->getConnectionName())->transaction(function () use ($project, $by, $requires4mcn, $remarks) {
            $project->fill([
                'requires_4mcn'      => $requires4mcn,
                'completion_remarks' => $remarks,
                'completed_at'       => now(),
            ])->save();

            $this->routeForApproval($project);

            return $this->changeStatus($project, TebProject::STATUS_FOR_APPROVAL, $by, 'TEB form completed and routed for approval');
        });
    }

    // Builds the approval section: one signature per approving department, from any of its
    // Supervisors, Section Heads or Managers. Departments sign at the same time.
    public function routeForApproval(TebProject $project): void
    {
        $levels      = array_keys(config('teb.approval_levels'));
        $departments = $this->approvingDepartments($project);

        // A department with nobody at any level could never sign, so that blocks completion.
        $missing = array_column(array_filter($departments, function ($dept) use ($levels) {
            foreach ($levels as $level) {
                if (ApproverCandidates::eligible($dept['id'], $level)) {
                    return false;
                }
            }

            return true;
        }), 'name');
        if ($missing) {
            throw ValidationException::withMessages([
                'approval' => 'No Supervisor, Section Head or Manager in the masterlist for: ' . implode(', ', $missing) . '. Check their department and job level in the masterlist.',
            ]);
        }

        $project->approvals()->delete();

        foreach ($departments as $order => $dept) {
            $project->approvals()->create([
                'department_group'  => $dept['group'],
                'department_id'     => $dept['id'],
                'dep_name'          => $dept['name'],
                'department_order'  => $order,
                'level'             => TebApproval::ANY_LEVEL,
                'level_order'       => 1,
                'approver_employid' => null,
                'approver_name'     => null,
                'status'            => TebApproval::PENDING,
            ]);
        }
    }

    // Fixed departments from config, then "Others": the project owner's department when it is not one of them.
    public function approvingDepartments(TebProject $project): array
    {
        $fixed = config('teb.approval_departments');
        $found = Department::whereIn('dep_name', $fixed)->pluck('id', 'dep_name');

        $rows = [];
        foreach ($fixed as $name) {
            $rows[] = ['group' => config("teb.approval_department_labels.{$name}", $name), 'id' => $found[$name] ?? null, 'name' => $name];
        }

        $owner = trim((string) $project->owner_department);
        $isFixed = in_array(mb_strtolower($owner), array_map('mb_strtolower', $fixed), true);
        if ($owner !== '' && ! $isFixed) {
            $dept = Department::whereRaw('LOWER(dep_name) = ?', [mb_strtolower($owner)])->first(['id', 'dep_name']);
            $rows[] = ['group' => 'Others: ' . ($dept->dep_name ?? $owner), 'id' => $dept?->id, 'name' => $dept->dep_name ?? $owner];
        }

        return $rows;
    }

    public function act(TebApproval $approval, TebActor $by, string $decision, ?string $remarks): TebProject
    {
        $project = $approval->project;

        if ($project->status !== TebProject::STATUS_FOR_APPROVAL) {
            throw ValidationException::withMessages(['approval' => 'This TEB is not for approval.']);
        }

        if (! $approval->canBeSignedBy($by)) {
            throw ValidationException::withMessages(['approval' => "Only a {$approval->level_label} of {$approval->dep_name} can sign this."]);
        }

        if (! $approval->isTurn()) {
            throw ValidationException::withMessages(['approval' => 'The previous level has not signed yet.']);
        }

        if ($decision === TebApproval::REJECTED && ! $remarks) {
            throw ValidationException::withMessages(['remarks' => 'Remarks are required when disapproving.']);
        }

        return DB::connection($project->getConnectionName())->transaction(function () use ($approval, $project, $by, $decision, $remarks) {
            $approval->update([
                'status'            => $decision,
                'remarks'           => $remarks,
                'acted_at'          => now(),
                'approver_employid' => $by->id,
                'approver_name'     => $this->nameOf($by->id) ?? $by->name,
            ]);

            $label = $approval->department_group;

            if ($decision === TebApproval::REJECTED) {
                return $this->changeStatus($project, TebProject::STATUS_RETURNED, $by, "Disapproved by {$label}: {$remarks}");
            }

            if (! $project->approvals()->where('status', '!=', TebApproval::APPROVED)->exists()) {
                $project->approved_at = now();

                return $this->changeStatus($project, TebProject::STATUS_APPROVED, $by, "Final approval by {$label}");
            }

            return $project;
        });
    }

    public function nameOf(?string $employid): ?string
    {
        if (! $employid) {
            return null;
        }

        $e = MasterEmployee::find($employid);

        return $e ? trim("{$e->firstname} {$e->lastname}") : null;
    }
}
