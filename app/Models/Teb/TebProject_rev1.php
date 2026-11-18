<?php
// app/Models/Teb/TebProject.php

namespace App\Models\Teb;

use App\Models\Masterlist\Employee;
use Illuminate\Database\Eloquent\Builder;

class TebProject extends TebModel
{
    protected $table = 'teb_projects';

    const STATUS_SUBMITTED          = 'submitted_for_review';
    const STATUS_OPEN               = 'open';
    const STATUS_HOLD               = 'hold';
    const STATUS_ONGOING            = 'ongoing';
    const STATUS_PENDING_TEB_NUMBER = 'pending_teb_number';
    const STATUS_REVIEWED           = 'reviewed';
    const STATUS_FOR_APPROVAL       = 'for_approval';
    const STATUS_RETURNED           = 'returned';
    const STATUS_APPROVED           = 'approved';

    // TRB process (A. Initiate & Plan, B. Validate & Approve, C. Implement & Close).
    const STATUS_REVISE             = 'revise';
    const STATUS_DROPPED            = 'dropped';
    const STATUS_PLAN_FOR_APPROVAL  = 'plan_for_approval';
    const STATUS_PLAN_RETURNED      = 'plan_returned';
    const STATUS_TRIAL_AUTHORIZED   = 'trial_authorized';
    const STATUS_ADI_REVIEW         = 'adi_review';
    const STATUS_CUSTOMER_HOLD      = 'customer_hold';
    const STATUS_DOC_RELEASE        = 'doc_release';
    const STATUS_READINESS_GAPS     = 'readiness_gaps';
    const STATUS_IMPLEMENTATION     = 'implementation';
    const STATUS_EFFECTIVENESS      = 'effectiveness';
    const STATUS_CONTAINMENT        = 'containment';
    const STATUS_CLOSED             = 'closed';

    const STATUS_LABELS = [
        self::STATUS_SUBMITTED          => 'Submitted for Review',
        self::STATUS_OPEN               => 'Open',
        self::STATUS_HOLD               => 'Hold',
        self::STATUS_ONGOING            => 'Ongoing',
        self::STATUS_REVISE             => 'Revise',
        self::STATUS_DROPPED            => 'Closed (Do not proceed)',
        self::STATUS_PENDING_TEB_NUMBER => 'Pending TRB Number',
        self::STATUS_REVIEWED           => 'Reviewed (Feasible)',
        self::STATUS_PLAN_FOR_APPROVAL  => 'Trial Plan for Approval',
        self::STATUS_PLAN_RETURNED      => 'Trial Plan Returned',
        self::STATUS_TRIAL_AUTHORIZED   => 'Trial Authorized',
        self::STATUS_FOR_APPROVAL       => 'Results for TSPI Approval',
        self::STATUS_RETURNED           => 'Results Returned',
        self::STATUS_APPROVED           => 'Validated (TSPI Approved)',
        self::STATUS_ADI_REVIEW         => 'ADI Review & 4MCN',
        self::STATUS_CUSTOMER_HOLD      => 'Hold: Customer Findings',
        self::STATUS_DOC_RELEASE        => 'Document Release',
        self::STATUS_READINESS_GAPS     => 'Readiness Gaps',
        self::STATUS_IMPLEMENTATION     => 'Controlled Implementation',
        self::STATUS_EFFECTIVENESS      => 'Effectiveness Verification',
        self::STATUS_CONTAINMENT        => 'Containment & Corrective Action',
        self::STATUS_CLOSED             => 'Project Closed',
    ];

    // Step of the TRB process each status belongs to (1-10); Project Closed is past step 10.
    const STATUS_STEPS = [
        self::STATUS_SUBMITTED          => 2,
        self::STATUS_OPEN               => 3,
        self::STATUS_HOLD               => 3,
        self::STATUS_ONGOING            => 3,
        self::STATUS_REVISE             => 3,
        self::STATUS_DROPPED            => 3,
        self::STATUS_PENDING_TEB_NUMBER => 3,
        self::STATUS_REVIEWED           => 4,
        self::STATUS_PLAN_FOR_APPROVAL  => 4,
        self::STATUS_PLAN_RETURNED      => 4,
        self::STATUS_TRIAL_AUTHORIZED   => 5,
        self::STATUS_FOR_APPROVAL       => 6,
        self::STATUS_RETURNED           => 6,
        self::STATUS_CONTAINMENT        => 6,
        self::STATUS_APPROVED           => 7,
        self::STATUS_ADI_REVIEW         => 7,
        self::STATUS_CUSTOMER_HOLD      => 7,
        self::STATUS_DOC_RELEASE        => 8,
        self::STATUS_READINESS_GAPS     => 8,
        self::STATUS_IMPLEMENTATION     => 9,
        self::STATUS_EFFECTIVENESS      => 10,
        self::STATUS_CLOSED             => 11,
    ];

    // What the TRB Admin may pick on "Update Status" (step 3 Feasibility and Change Assessment).
    // Reviewed = Feasible, Revise = back to the originator, Closed (Do not proceed) ends the project.
    const REVIEW_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_HOLD,
        self::STATUS_ONGOING,
        self::STATUS_REVIEWED,
        self::STATUS_REVISE,
        self::STATUS_DROPPED,
    ];

    // Statuses in which the TRB Admin may still schedule reviews and update the review status.
    const PRE_FEASIBILITY_STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_OPEN,
        self::STATUS_HOLD,
        self::STATUS_ONGOING,
        self::STATUS_REVISE,
        self::STATUS_PENDING_TEB_NUMBER,
    ];

    // The originator may still edit the entry in these statuses.
    const EDITABLE_STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_OPEN,
        self::STATUS_HOLD,
        self::STATUS_REVISE,
    ];

    // Projects that have finished the process one way or the other.
    const ENDED_STATUSES = [
        self::STATUS_DROPPED,
        self::STATUS_CLOSED,
    ];

    // Step 1 Project Charter (typed); stored in the charter json column with 'bmp_drivers' (config teb.bmp_drivers).
    const CHARTER_FIELDS = [
        'problem_statement'  => 'Problem / Opportunity Statement',
        'business_case'      => 'Business Case',
        'goal_statement'     => 'Goal Statement (SMART)',
        'project_scope'      => 'Project Scope',
        'baseline_kpi'       => 'Baseline KPI and Target',
        'team_leader'        => 'Team Leader',
        'members'            => 'Members',
        'timeline'           => 'Timeline',
        'financial_benefits' => 'Financial Benefits and Budget',
        'sponsor_name'       => 'Sponsor(s)',
    ];

    // Members and Sponsor(s) are picked from the masterlist: their ids are kept beside the names above.
    const CHARTER_PEOPLE = ['members' => 'member_ids', 'sponsor_name' => 'sponsor_ids'];

    const CHARTER_REQUIRED = ['problem_statement', 'goal_statement', 'project_scope'];

    // BMP drivers picked with their category, e.g. ['T' => 'Machine & Process Qualification'].
    // Major when any picked category is Major (BMP maintenance, TebBmpCategory), otherwise Minor; null when none is picked.
    public static function classificationFor(array $bmp): ?string
    {
        $classes = collect($bmp)->map(fn ($category, $driver) => (TebBmpCategory::map()[$driver] ?? [])[$category] ?? null)->filter();

        return $classes->isEmpty() ? null : ($classes->contains('major') ? 'major' : 'minor');
    }

    protected $fillable = [
        'teb_no',
        'title',
        'category',
        'customer_id',
        'customer_ids',
        'customer_name',
        'classification',
        'types_of_change',
        'project_description',
        'current_status',
        'change_description',
        'charter',
        'owner_employid',
        'owner_name',
        'owner_department',
        'status',
        'requires_4mcn',
        'customer_approval_required',
        'completion_remarks',
        'teb_no_assigned_at',
        'plan_submitted_at',
        'completed_at',
        'approved_at',
        'implemented_at',
        'closed_at',
        'gate_records',
    ];

    protected $casts = [
        'types_of_change'            => 'array',
        'charter'                    => 'array',
        'customer_ids'               => 'array',
        'requires_4mcn'              => 'boolean',
        'customer_approval_required' => 'boolean',
        'teb_no_assigned_at'         => 'datetime',
        'plan_submitted_at'          => 'datetime',
        'completed_at'               => 'datetime',
        'approved_at'                => 'datetime',
        'implemented_at'             => 'datetime',
        'closed_at'                  => 'datetime',
        'gate_records'               => 'array',
    ];

    public function affectedAreas()
    {
        return $this->hasMany(TebAffectedArea::class, 'teb_project_id');
    }

    public function attachments()
    {
        return $this->hasMany(TebAttachment::class, 'teb_project_id');
    }

    public function reviews()
    {
        return $this->hasMany(TebReview::class, 'teb_project_id')->orderByDesc('review_at');
    }

    public function statusLogs()
    {
        return $this->hasMany(TebStatusLog::class, 'teb_project_id')->orderBy('id');
    }

    public function approvals()
    {
        return $this->hasMany(TebApproval::class, 'teb_project_id')->orderBy('department_order')->orderBy('level_order');
    }

    public function fourMcn()
    {
        return $this->hasOne(TebFourMcn::class, 'teb_project_id');
    }

    public function owner()
    {
        return $this->belongsTo(Employee::class, 'owner_employid', 'employid');
    }

    public function scopeStatus(Builder $query, ?string $status)
    {
        return $status ? $query->where('status', $status) : $query;
    }

    // Filter by BMP driver (T/Q/R/D/C) picked in the charter; an older category key still matches its entries.
    public function scopeCategory(Builder $query, ?string $category)
    {
        if (! $category) {
            return $query;
        }

        return array_key_exists($category, config('teb.bmp_drivers'))
            ? $query->whereNotNull("charter->bmp->{$category}")
            : $query->where('category', $category);
    }

    public function scopeSearch(Builder $query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('teb_no', 'like', "%{$term}%")
                ->orWhere('title', 'like', "%{$term}%")
                ->orWhere('customer_name', 'like', "%{$term}%")
                ->orWhere('owner_name', 'like', "%{$term}%");
        });
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getCategoryLabelAttribute(): string
    {
        // Entries registered before the BMP Alignment keep their category; newer ones show their BMP categories.
        if ($this->category) {
            return config('teb.categories')[$this->category] ?? (string) $this->category;
        }

        return implode(', ', array_values((array) ($this->charter['bmp'] ?? [])));
    }

    public function getClassificationLabelAttribute(): string
    {
        return config('teb.classifications')[$this->classification] ?? (string) $this->classification;
    }

    public function getTypesOfChangeLabelAttribute(): string
    {
        $labels = config('teb.types_of_change');

        return collect($this->types_of_change ?? [])->map(fn ($t) => $labels[$t] ?? $t)->implode(', ');
    }

    public function getAffectedAreasLabelAttribute(): string
    {
        return $this->covers_all_departments ? 'All departments' : $this->affectedAreas->pluck('dep_name')->implode(', ');
    }

    // True when every masterlist department was picked as an affected area.
    public function getCoversAllDepartmentsAttribute(): bool
    {
        // Counted once per request, not once per project row.
        $attrs = request()->attributes;
        if (! $attrs->has('teb.department_count')) {
            $attrs->set('teb.department_count', \App\Models\Lookups\Department::count());
        }
        $total = $attrs->get('teb.department_count');

        return $total > 0 && $this->affectedAreas->pluck('department_id')->unique()->count() >= $total;
    }

    public function getDisplayNoAttribute(): string
    {
        return $this->teb_no ?: 'Pending';
    }

    public function isMajor(): bool
    {
        return $this->classification === 'major';
    }

    // A TRB can be set to Reviewed only after a scheduled review has taken place
    // (its date/time plus duration has passed). Returns why not, or null when it can.
    public function reviewBlockReason(): ?string
    {
        $reviews = $this->reviews()->get();
        if ($reviews->isEmpty()) {
            return 'Schedule a TRB review first. It can be marked Reviewed once the review has taken place.';
        }

        $held = $reviews->contains(fn (TebReview $r) => $r->review_at->copy()->addMinutes((int) $r->duration_minutes)->lte(now()));
        if (! $held) {
            $next = $reviews->sortBy('review_at')->first();

            return 'The TRB review on '.$next->review_at->format('m/d/Y H:i').' has not taken place yet. It can be marked Reviewed after it ends.';
        }

        return null;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE_STATUSES, true);
    }

    public function step(): int
    {
        return self::STATUS_STEPS[$this->status] ?? 1;
    }

    // Step 4: the originator submits the trial plan once the TRB is feasible and numbered.
    public function canSubmitPlan(): bool
    {
        return $this->teb_no && in_array($this->status, [self::STATUS_REVIEWED, self::STATUS_PLAN_RETURNED], true);
    }

    // Step 6: the originator uploads the TRB Report (results) after the trial, after findings,
    // or after containment when the effectiveness check failed.
    public function canBeCompleted(): bool
    {
        return in_array($this->status, [self::STATUS_TRIAL_AUTHORIZED, self::STATUS_RETURNED, self::STATUS_CONTAINMENT], true);
    }

    // Which approval round is open: 'plan' (step 4) or 'results' (step 6).
    public function approvalRound(): ?string
    {
        return match ($this->status) {
            self::STATUS_PLAN_FOR_APPROVAL => 'plan',
            self::STATUS_FOR_APPROVAL      => 'results',
            default                        => null,
        };
    }

    // The 4MCN can be generated once the results are approved, until the project closes.
    public function needsFourMcn(): bool
    {
        return $this->requires_4mcn && ! $this->fourMcn && $this->step() >= 7 && $this->step() <= 10;
    }

    // 4MCN Current Status (before change) = charter Problem / Opportunity Statement. Older entries without a charter
    // fall back to the Current Status typed on the old register form.
    public function fourMcnBeforeChange(): string
    {
        return trim((string) ($this->charter['problem_statement'] ?? '')) ?: ($this->current_status ?: '—');
    }

    // 4MCN Change Description (after change) = charter Project Scope; older entries fall back to Change Description / description.
    public function fourMcnAfterChange(): string
    {
        return trim((string) ($this->charter['project_scope'] ?? '')) ?: ($this->change_description ?: ($this->project_description ?: '—'));
    }

    public function attachmentsOf(string $type)
    {
        return $this->attachments->where('type', $type);
    }

    // Auto-generated yyyy-NNNNN (e.g. 2026-00001); sequence restarts every year.
    public static function nextTebNo(?int $year = null): string
    {
        $year = $year ?: (int) now()->format('Y');

        $last = static::where('teb_no', 'like', "{$year}-%")->orderByDesc('teb_no')->value('teb_no');
        $seq  = $last ? ((int) substr($last, 5)) + 1 : 1;

        return sprintf('%d-%0'.config('teb.teb_no_digits', 5).'d', $year, $seq);
    }
}
