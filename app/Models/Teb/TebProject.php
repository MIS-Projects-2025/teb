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

    const STATUS_LABELS = [
        self::STATUS_SUBMITTED          => 'Submitted for Review',
        self::STATUS_OPEN               => 'Open',
        self::STATUS_HOLD               => 'Hold',
        self::STATUS_ONGOING            => 'Ongoing',
        self::STATUS_PENDING_TEB_NUMBER => 'Pending TEB Number',
        self::STATUS_REVIEWED           => 'Reviewed',
        self::STATUS_FOR_APPROVAL       => 'For Approval',
        self::STATUS_RETURNED           => 'Returned to Originator',
        self::STATUS_APPROVED           => 'Approved',
    ];

    // What the TEB Admin may pick on "Update Status" (slide 3).
    const REVIEW_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_HOLD,
        self::STATUS_ONGOING,
        self::STATUS_REVIEWED,
    ];

    // The originator may still edit the entry in these statuses.
    const EDITABLE_STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_OPEN,
        self::STATUS_HOLD,
    ];

    protected $fillable = [
        'teb_no',
        'title',
        'category',
        'customer_id',
        'customer_name',
        'classification',
        'types_of_change',
        'project_description',
        'current_status',
        'change_description',
        'owner_employid',
        'owner_name',
        'owner_department',
        'status',
        'requires_4mcn',
        'completion_remarks',
        'teb_no_assigned_at',
        'completed_at',
        'approved_at',
    ];

    protected $casts = [
        'types_of_change'    => 'array',
        'requires_4mcn'      => 'boolean',
        'teb_no_assigned_at' => 'datetime',
        'completed_at'       => 'datetime',
        'approved_at'        => 'datetime',
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

    public function scopeCategory(Builder $query, ?string $category)
    {
        return $category ? $query->where('category', $category) : $query;
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
        return config('teb.categories')[$this->category] ?? (string) $this->category;
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

    // A TEB can be set to Reviewed only after a scheduled review has taken place
    // (its date/time plus duration has passed). Returns why not, or null when it can.
    public function reviewBlockReason(): ?string
    {
        $reviews = $this->reviews()->get();
        if ($reviews->isEmpty()) {
            return 'Schedule a TEB review first. It can be marked Reviewed once the review has taken place.';
        }

        $held = $reviews->contains(fn (TebReview $r) => $r->review_at->copy()->addMinutes((int) $r->duration_minutes)->lte(now()));
        if (! $held) {
            $next = $reviews->sortBy('review_at')->first();

            return 'The TEB review on '.$next->review_at->format('m/d/Y H:i').' has not taken place yet. It can be marked Reviewed after it ends.';
        }

        return null;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE_STATUSES, true);
    }

    // Originator may generate the completed TEB form once a number exists.
    public function canBeCompleted(): bool
    {
        return in_array($this->status, [self::STATUS_REVIEWED, self::STATUS_RETURNED], true);
    }

    public function needsFourMcn(): bool
    {
        return $this->status === self::STATUS_APPROVED && $this->requires_4mcn && ! $this->fourMcn;
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
