<?php
// app/Models/Teb/TebApproval.php

namespace App\Models\Teb;

use App\Models\Masterlist\Employee;

// One signature box in the approval section: one per department (level 'any'), signed by any
// Supervisor, Section Head or Manager of that department. Older rows may carry a single level.
class TebApproval extends TebModel
{
    protected $table = 'teb_approvals';

    const PENDING  = 'pending';
    const APPROVED = 'approved';
    const REJECTED = 'rejected';

    const ANY_LEVEL = 'any';

    protected $fillable = [
        'teb_project_id',
        'department_group',
        'department_id',
        'dep_name',
        'department_order',
        'level',
        'level_order',
        'approver_employid',
        'approver_name',
        'status',
        'remarks',
        'acted_at',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(TebProject::class, 'teb_project_id');
    }

    public function approver()
    {
        return $this->belongsTo(Employee::class, 'approver_employid', 'employid');
    }

    public function getLevelLabelAttribute(): string
    {
        return $this->level === self::ANY_LEVEL
            ? implode(' / ', config('teb.approval_levels'))
            : (config('teb.approval_levels')[$this->level] ?? $this->level);
    }

    // Approvers are not named in advance: anyone active in the department with the matching
    // job / position level (masterlist) may sign. The signer is recorded on the row.
    public function canBeSignedBy(?\App\Services\Teb\TebActor $user): bool
    {
        if (! $user) {
            return false;
        }
        $dept   = $this->department_id ? (int) $this->department_id : null;
        $levels = $this->level === self::ANY_LEVEL ? array_keys(config('teb.approval_levels')) : [$this->level];

        foreach ($levels as $level) {
            if (\App\Services\Teb\ApproverCandidates::canSign($user->id, $dept, $level)) {
                return true;
            }
        }

        return false;
    }

    // Pending rows this employee may sign (ignores turn order).
    public function scopeSignableBy($query, \App\Services\Teb\TebActor $user)
    {
        $pairs = \App\Services\Teb\ApproverCandidates::signableBy($user->id);

        return $query->where('status', self::PENDING)->where(function ($q) use ($pairs) {
            $q->whereRaw('1 = 0');
            foreach ($pairs as [$dept, $level]) {
                $q->orWhere(fn($w) => $w->where('department_id', $dept)->whereIn('level', [$level, self::ANY_LEVEL]));
            }
        });
    }

    // Departments sign at the same time. Older per-level rows: lower levels of the same department sign first.
    public function isTurn(): bool
    {
        if ($this->status !== self::PENDING) {
            return false;
        }

        return ! static::where('teb_project_id', $this->teb_project_id)
            ->where('department_group', $this->department_group)
            ->where('level_order', '<', $this->level_order)
            ->where('status', '!=', self::APPROVED)
            ->exists();
    }
}
