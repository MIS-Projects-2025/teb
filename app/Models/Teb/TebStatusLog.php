<?php
// app/Models/Teb/TebStatusLog.php

namespace App\Models\Teb;

class TebStatusLog extends TebModel
{
    protected $table = 'teb_status_logs';
    protected $fillable = ['teb_project_id', 'from_status', 'to_status', 'remarks', 'employid', 'emp_name'];

    public function project()
    {
        return $this->belongsTo(TebProject::class, 'teb_project_id');
    }

    public function getToLabelAttribute(): string
    {
        return TebProject::STATUS_LABELS[$this->to_status] ?? $this->to_status;
    }

    public function getFromLabelAttribute(): ?string
    {
        return $this->from_status ? (TebProject::STATUS_LABELS[$this->from_status] ?? $this->from_status) : null;
    }
}
