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

    // Slide 3 "Update status". Reviewed without a TRB No. waits on "Assign TRB Number".
    public function updateReviewStatus(TebProject $project, string $to, TebActor $by, ?string $remarks, array $assessment = []): TebProject
    {
        if (! in_array($to, TebProject::REVIEW_STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Invalid review status.']);
        }

        if (! in_array($project->status, TebProject::PRE_FEASIBILITY_STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'The feasibility decision for this TRB has already been made.']);
        }

        if ($to === TebProject::STATUS_REVIEWED && ($reason = $project->reviewBlockReason())) {
            throw ValidationException::withMessages(['status' => $reason]);
        }

        if ($to === TebProject::STATUS_REVISE && ! $remarks) {
            throw ValidationException::withMessages(['remarks' => 'Tell the originator what to revise.']);
        }

        if ($to === TebProject::STATUS_DROPPED) {
            $project->closed_at = now();
        }

        // Gate 1: the board's assessment goes with the decision (proceed, revise or stop).
        if (in_array($to, [TebProject::STATUS_REVIEWED, TebProject::STATUS_REVISE, TebProject::STATUS_DROPPED], true)) {
            TebGates::record($project, 'feasibility', array_intersect_key($assessment, TebGates::GATES['feasibility']['fields']), $by);
        }

        if ($to === TebProject::STATUS_REVIEWED && ! $project->teb_no) {
            $to = TebProject::STATUS_PENDING_TEB_NUMBER;
        }

        return $this->changeStatus($project, $to, $by, $remarks);
    }

    // TRB No. is always system generated for now.
    public function assignTebNo(TebProject $project, TebActor $by): TebProject
    {
        if ($project->teb_no) {
            throw ValidationException::withMessages(['teb_no' => 'A TRB No. is already assigned.']);
        }

        return DB::connection($project->getConnectionName())->transaction(function () use ($project, $by) {
            $project->teb_no = TebProject::nextTebNo();
            $project->teb_no_assigned_at = now();
            $project->save();

            $next = $project->status === TebProject::STATUS_PENDING_TEB_NUMBER
                ? TebProject::STATUS_REVIEWED
                : $project->status;

            return $this->changeStatus($project, $next, $by, "TRB No. {$project->teb_no} assigned");
        });
    }

    // Revise -> the originator edits the entry and it goes back for review.
    public function resubmitRevision(TebProject $project, TebActor $by): TebProject
    {
        return $project->status === TebProject::STATUS_REVISE
            ? $this->changeStatus($project, TebProject::STATUS_SUBMITTED, $by, 'Revised and resubmitted for review')
            : $project;
    }

    // Step 4: Development and Qualification Planning. The trial plan goes to the approval section
    // (or to the TRB Admin, config teb.plan_approval = admin).
    public function submitPlan(TebProject $project, TebActor $by, ?string $remarks, array $plan = []): TebProject
    {
        if (! $project->canSubmitPlan()) {
            throw ValidationException::withMessages(['status' => 'The trial plan can be submitted once the TRB is feasible and has a TRB No.']);
        }

        if (! $project->attachments()->where('type', 'trial_plan')->exists()) {
            throw ValidationException::withMessages(['trial_plan' => 'Upload the trial / qualification plan first.']);
        }

        return DB::connection($project->getConnectionName())->transaction(function () use ($project, $by, $remarks, $plan) {
            $project->plan_submitted_at = now();
            $project->save();
            TebGates::record($project, 'plan', array_intersect_key($plan, TebGates::GATES['plan']['fields']), $by);

            if (config('teb.plan_approval') === 'departments') {
                $this->routeForApproval($project, TebApproval::ROUND_PLAN);
            } else {
                $project->approvals()->where('round', TebApproval::ROUND_PLAN)->delete();
            }

            return $this->changeStatus($project, TebProject::STATUS_PLAN_FOR_APPROVAL, $by, trim('Trial plan submitted for approval. '.$remarks));
        });
    }

    // Steps 7 to 10 and the admin plan decision: one TRB Admin (or originator) action moves the project on.
    // Each action is only valid from the statuses listed; see STAGE_ACTIONS.
    const STAGE_ACTIONS = [
        'plan'            => [TebProject::STATUS_PLAN_FOR_APPROVAL],  // yes: Trial Authorized, no: Trial Plan Returned (admin mode)
        'customer'        => [TebProject::STATUS_APPROVED],           // Customer approval required? yes: ADI Review, no: Document Release
        'secured'         => [TebProject::STATUS_ADI_REVIEW],         // Customer approval secured? yes: Document Release, no: Hold
        'resolved'        => [TebProject::STATUS_CUSTOMER_HOLD],      // customer findings resolved: back to ADI Review
        'material_change' => [TebProject::STATUS_ADI_REVIEW, TebProject::STATUS_CUSTOMER_HOLD], // back to TRB (step 6) for reassessment
        'notify_adi'      => [TebProject::STATUS_ADI_REVIEW],         // email the ADI Oversight Team (stays in ADI Review)
        'readiness'       => [TebProject::STATUS_DOC_RELEASE],        // Gate 2 checklist: all ready -> Implementation, else Gaps
        'gaps_closed'     => [TebProject::STATUS_READINESS_GAPS],     // back to Document Release
        'implemented'     => [TebProject::STATUS_IMPLEMENTATION],     // implementation record -> Effectiveness Verification
        'effectiveness'   => [TebProject::STATUS_EFFECTIVENESS],      // PIEV met and closure approved? yes: Project Closed, no: Containment
    ];

    public function stage(TebProject $project, TebActor $by, string $action, ?bool $yes, ?string $remarks, array $checks = [], array $fields = []): TebProject
    {
        if (! in_array($project->status, self::STAGE_ACTIONS[$action] ?? [], true)) {
            throw ValidationException::withMessages(['stage' => 'This step is not open for this TRB.']);
        }
        if ($action === 'plan' && config('teb.plan_approval') === 'departments') {
            throw ValidationException::withMessages(['stage' => 'The trial plan is approved in the approval section.']);
        }

        $needsRemarks = ($action === 'plan' && ! $yes) || ($action === 'secured' && ! $yes) || ($action === 'effectiveness' && ! $yes);
        if ($needsRemarks && ! $remarks) {
            throw ValidationException::withMessages(['remarks' => 'Remarks are required: say what needs to be addressed.']);
        }

        $note = fn (string $text) => trim($text.($remarks ? ". {$remarks}" : ''));

        switch ($action) {
            case 'plan':
                return $yes
                    ? $this->changeStatus($project, TebProject::STATUS_TRIAL_AUTHORIZED, $by, $note('Trial plan approved. Trial and validation authorized'))
                    : $this->changeStatus($project, TebProject::STATUS_PLAN_RETURNED, $by, $note('Trial plan returned: address gaps and resubmit'));

            case 'customer':
                $project->customer_approval_required = (bool) $yes;
                $project->save();

                return $yes
                    ? $this->changeStatus($project, TebProject::STATUS_ADI_REVIEW, $by, $note('Customer approval required: ADI review and applicable 4MCN approval'))
                    : $this->changeStatus($project, TebProject::STATUS_DOC_RELEASE, $by, $note('No customer approval required'));

            case 'notify_adi':
                $emails = collect($fields['emails'] ?? [])->map(fn ($e) => mb_strtolower(trim($e)))->filter()->unique()->values();
                if ($emails->isEmpty()) {
                    throw ValidationException::withMessages(['emails' => 'Enter at least one ADI Oversight Team email address.']);
                }
                $sent = 0;
                $error = null;
                foreach ($emails as $email) {
                    try {
                        \Illuminate\Support\Facades\Mail::to($email)->send(new \App\Mail\TebAdiNotification($project, $fields['presentation_at'] ?? null, $fields['location'] ?? null, $remarks));
                        $sent++;
                    } catch (\Throwable $e) {
                        report($e);
                        $error ??= $e->getMessage();
                    }
                }
                TebGates::record($project, 'customer', [
                    'adi_notified'     => $emails->implode(', '),
                    'adi_presentation' => trim(($fields['presentation_at'] ?? '').' '.($fields['location'] ?? '')),
                ], $by);
                $project->statusLogs()->create(['from_status' => $project->status, 'to_status' => $project->status, 'employid' => $by->id, 'emp_name' => $by->name,
                    'remarks' => "ADI Oversight Team notified ({$sent} of {$emails->count()} sent)".($error ? '. Mail error: '.str($error)->limit(200) : '')]);
                if ($error) {
                    throw ValidationException::withMessages(['emails' => "{$sent} of {$emails->count()} notice(s) sent. Mail error: ".str($error)->limit(300)]);
                }

                return $project;

            case 'secured':
                // TFP16-008 step 7: the 4MCN is generated by the system once ADI approves.
                if ($yes && $project->requires_4mcn && ! $project->fourMcn()->exists()) {
                    $this->generateFourMcn($project, $by);
                }

                if ($yes) {
                    TebGates::record($project, 'customer', ['conditions' => $fields['conditions'] ?? null], $by);
                }

                return $yes
                    ? $this->changeStatus($project, TebProject::STATUS_DOC_RELEASE, $by, $note('Customer approval secured'))
                    : $this->changeStatus($project, TebProject::STATUS_CUSTOMER_HOLD, $by, $note('Customer approval not secured: hold and resolve customer findings'));

            case 'resolved':
                return $this->changeStatus($project, TebProject::STATUS_ADI_REVIEW, $by, $note('Customer findings resolved'));

            case 'material_change':
                if (! $remarks) {
                    throw ValidationException::withMessages(['remarks' => 'Describe the material change.']);
                }

                return $this->changeStatus($project, TebProject::STATUS_RETURNED, $by, $note('Material change from customer review: returned to TRB for reassessment'));

            case 'readiness':
                $checks = array_map('boolval', array_intersect_key($checks, TebGates::READINESS_CHECKS));
                if (! empty($checks['documents_released']) && empty($fields['swi_spec_numbers'])) {
                    throw ValidationException::withMessages(['swi_spec_numbers' => 'Enter the released SWI / specification numbers.']);
                }
                TebGates::record($project, 'readiness', ['swi_spec_numbers' => $fields['swi_spec_numbers'] ?? null, 'checks' => $checks], $by);
                $missing = array_values(array_diff_key(TebGates::READINESS_CHECKS, array_filter($checks)));

                return $missing
                    ? $this->changeStatus($project, TebProject::STATUS_READINESS_GAPS, $by, $note('Readiness gaps: '.implode(', ', $missing)))
                    : $this->changeStatus($project, TebProject::STATUS_IMPLEMENTATION, $by, $note('Gate 2 passed: documents released, training, equipment, materials and approval conditions ready'));

            case 'gaps_closed':
                return $this->changeStatus($project, TebProject::STATUS_DOC_RELEASE, $by, $note('Readiness gaps closed'));

            case 'implemented':
                foreach (['effective_date' => 'Enter the effective date.', 'affected_machines_lots' => 'List the affected machines / lots.'] as $key => $msg) {
                    if (empty($fields[$key])) {
                        throw ValidationException::withMessages([$key => $msg]);
                    }
                }
                $project->implemented_at = now();
                $project->save();
                TebGates::record($project, 'implementation', array_intersect_key($fields, TebGates::GATES['implementation']['fields']), $by);

                return $this->changeStatus($project, TebProject::STATUS_EFFECTIVENESS, $by, $note('Controlled implementation done'));

            case 'effectiveness':
                if ($yes) {
                    $piev = array_map('boolval', array_intersect_key($checks, TebGates::PIEV_CHECKS));
                    if (count(array_filter($piev)) < count(TebGates::PIEV_CHECKS)) {
                        throw ValidationException::withMessages(['checks' => 'Every PIEV acceptance criterion must be met to close the project.']);
                    }
                    if (empty($fields['verified_by'])) {
                        throw ValidationException::withMessages(['verified_by' => 'Enter who verified effectiveness (Engineering / QA).']);
                    }
                    if (! $project->attachments()->where('type', 'closure_report')->exists()) {
                        throw ValidationException::withMessages(['stage_file' => 'Attach the Effectiveness and Closure Report.']);
                    }
                    TebGates::record($project, 'closure', ['piev' => $piev, 'verified_by' => $fields['verified_by']], $by);
                    $project->closed_at = now();
                    $project->save();

                    return $this->changeStatus($project, TebProject::STATUS_CLOSED, $by, $note('Effectiveness criteria met and closure approved'));
                }

                return $this->changeStatus($project, TebProject::STATUS_CONTAINMENT, $by, $note('Effectiveness not met: contain issues and corrective actions, then repeat the results review'));
        }

        return $project;
    }

    // Step 6: the originator generates the completed TRB form with the results; the portal routes it for approval.
    public function complete(TebProject $project, TebActor $by, bool $requires4mcn, ?string $remarks, array $results = []): TebProject
    {
        if (! $project->canBeCompleted()) {
            throw ValidationException::withMessages(['status' => 'This TRB cannot be completed yet.']);
        }

        if (! $project->attachments()->where('type', 'teb_report')->exists()) {
            throw ValidationException::withMessages(['teb_report' => 'Upload the TRB Report first.']);
        }

        // Step 5: customer product may only be used with the customer's trial authorization.
        if (TebGates::value($project, 'plan', 'uses_customer_product') && ! $project->attachments()->where('type', 'customer_trial')->exists()) {
            throw ValidationException::withMessages(['customer_trial' => 'The trial uses customer product: attach the customer trial authorization.']);
        }

        return DB::connection($project->getConnectionName())->transaction(function () use ($project, $by, $requires4mcn, $remarks, $results) {
            TebGates::record($project, 'results', array_intersect_key($results, TebGates::GATES['results']['fields']), $by);
            $project->fill([
                'requires_4mcn'      => $requires4mcn,
                'completion_remarks' => $remarks,
                'completed_at'       => now(),
            ])->save();

            $this->routeForApproval($project, TebApproval::ROUND_RESULTS);

            return $this->changeStatus($project, TebProject::STATUS_FOR_APPROVAL, $by, 'TRB Report submitted: results routed for TSPI approval');
        });
    }

    // Builds the approval section: one signature per approving department, from any of its
    // Supervisors, Section Heads or Managers. Departments sign at the same time.
    // $keepSigned: rebuild in place (teb:reroute), keeping boxes already signed and adding departments that are new
    // in config (e.g. QMS); otherwise the round starts over.
    public function routeForApproval(TebProject $project, string $round = TebApproval::ROUND_RESULTS, bool $keepSigned = false): void
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
        $executives = in_array($project->classification, config('teb.executive_approval_classifications', []), true)
            ? config('teb.executive_approvals', [])
            : [];
        $noExecutive = array_values(array_map(fn ($key) => $executives[$key]['label'],
            array_filter(array_keys($executives), fn ($key) => ! ApproverCandidates::eligibleExecutive($key))));

        if ($missing || $noExecutive) {
            throw ValidationException::withMessages(['approval' => trim(
                ($missing ? 'No Supervisor, Section Head or Manager in the masterlist for: '.implode(', ', $missing).'. ' : '')
                .($noExecutive ? 'No '.implode(' or ', $noExecutive).' found in the masterlist (Major changes need them). ' : '')
                .'Check their department and job level in the masterlist.'
            )]);
        }

        $rows = [];
        foreach ($departments as $order => $dept) {
            $rows[] = ['department_group' => $dept['group'], 'department_id' => $dept['id'], 'dep_name' => $dept['name'],
                'department_order' => $order, 'level' => TebApproval::ANY_LEVEL, 'level_order' => 1];
        }
        // Major changes: Operation Director, then President, after all departments.
        foreach (array_keys($executives) as $i => $key) {
            $rows[] = ['department_group' => $executives[$key]['label'], 'department_id' => null, 'dep_name' => $executives[$key]['label'],
                'department_order' => count($departments) + $i, 'level' => $key, 'level_order' => 2 + $i];
        }

        $existing = $project->approvals()->where('round', $round)->get()->keyBy(fn ($a) => "{$a->department_group}|{$a->level}");
        if (! $keepSigned) {
            $project->approvals()->where('round', $round)->delete();
            $existing = collect();
        }

        $keys = [];
        foreach ($rows as $row) {
            $keys[] = $key = "{$row['department_group']}|{$row['level']}";
            if ($box = $existing->get($key)) {
                $box->update(['department_order' => $row['department_order'], 'level_order' => $row['level_order']]);
                continue;
            }
            $project->approvals()->create($row + ['round' => $round, 'approver_employid' => null, 'approver_name' => null, 'status' => TebApproval::PENDING]);
        }

        // Boxes no longer in the rules are dropped unless someone already signed them.
        $existing->reject(fn ($a, $key) => in_array($key, $keys, true) || $a->status !== TebApproval::PENDING)->each->delete();
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
            $rows[] = ['group' => 'Others: '.($dept->dep_name ?? $owner), 'id' => $dept?->id, 'name' => $dept->dep_name ?? $owner];
        }

        return $rows;
    }

    public function act(TebApproval $approval, TebActor $by, string $decision, ?string $remarks): TebProject
    {
        $project = $approval->project;

        if ($project->approvalRound() !== $approval->round) {
            throw ValidationException::withMessages(['approval' => 'This TRB is not for approval.']);
        }

        if (! $approval->canBeSignedBy($by)) {
            throw ValidationException::withMessages(['approval' => "Only a {$approval->level_label} of {$approval->dep_name} can sign this."]);
        }

        if (! $approval->isTurn()) {
            throw ValidationException::withMessages(['approval' => $approval->isExecutive()
                ? 'Every department, and the Operation Director before the President, must sign first.'
                : 'The previous level has not signed yet.']);
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
            $plan  = $approval->round === TebApproval::ROUND_PLAN;

            if ($decision === TebApproval::REJECTED) {
                return $plan
                    ? $this->changeStatus($project, TebProject::STATUS_PLAN_RETURNED, $by, "Trial plan disapproved by {$label}: {$remarks}")
                    : $this->changeStatus($project, TebProject::STATUS_RETURNED, $by, "Results disapproved by {$label}: {$remarks}");
            }

            if (! $project->approvals()->where('round', $approval->round)->where('status', '!=', TebApproval::APPROVED)->exists()) {
                if ($plan) {
                    return $this->changeStatus($project, TebProject::STATUS_TRIAL_AUTHORIZED, $by, "Trial plan approved (final: {$label}). Trial and validation authorized");
                }
                $project->approved_at = now();

                return $this->changeStatus($project, TebProject::STATUS_APPROVED, $by, "Results validated, TSPI approved (final: {$label})");
            }

            return $project;
        });
    }

    // 4MCN filled from the TRB entry (Current Status / Change Description from the charter); the originator can print it and route it for ADI signatures.
    public function generateFourMcn(TebProject $project, TebActor $by): void
    {
        $project->fourMcn()->create([
            'mcn_no'           => \App\Models\Teb\TebFourMcn::nextNo(),
            'title'            => $project->title,
            'change_items'     => [],
            'classification'   => $project->classification,
            'types_of_change'  => $project->types_of_change,
            'date_written'     => now()->toDateString(),
            'effectivity_date' => now()->toDateString(),
            'before_change'    => $project->fourMcnBeforeChange(),
            'after_change'     => $project->fourMcnAfterChange(),
            'reason'           => TebGates::value($project, 'results', 'conclusion') ?: ($project->charter['business_case'] ?? $project->project_description),
            'attachments_note' => 'TRB '.$project->teb_no.' report and qualification results',
            'prepared_by'      => $project->owner_employid,
            'prepared_name'    => $project->owner_name,
        ]);
        $project->unsetRelation('fourMcn');
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
