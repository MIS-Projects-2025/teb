<?php
// app/Models/Teb/TebAttachment.php

namespace App\Models\Teb;

class TebAttachment extends TebModel
{
    protected $table = 'teb_attachments';

    const TYPE_FMEA       = 'fmea';
    const TYPE_CHARTER    = 'charter';
    const TYPE_TEB_REPORT = 'teb_report';
    const TYPE_4MCN       = '4mcn';
    const TYPE_TRIAL_PLAN = 'trial_plan';
    const TYPE_CUSTOMER   = 'customer_approval';
    const TYPE_RELEASE    = 'release';
    const TYPE_CONTROL    = 'control_plan';
    const TYPE_DRAFT_SWI  = 'draft_swi';
    const TYPE_CUST_TRIAL = 'customer_trial';
    const TYPE_CLOSURE    = 'closure_report';
    const TYPE_OTHER      = 'other';

    const TYPE_LABELS = [
        self::TYPE_CHARTER    => 'Project Charter',
        self::TYPE_FMEA       => 'FMEA',
        self::TYPE_TRIAL_PLAN => 'Qualification / Trial Plan',
        self::TYPE_CONTROL    => 'Control Plan',
        self::TYPE_DRAFT_SWI  => 'Draft SWI / Spec Changes',
        self::TYPE_CUST_TRIAL => 'Customer Trial Authorization',
        self::TYPE_TEB_REPORT => 'TRB Report',
        self::TYPE_4MCN       => '4MCN Support',
        self::TYPE_CUSTOMER   => 'Customer Approval',
        self::TYPE_RELEASE    => 'Released SWI / Spec',
        self::TYPE_CLOSURE    => 'Effectiveness & Closure Report',
        self::TYPE_OTHER      => 'Other',
    ];

    protected $fillable = ['teb_project_id', 'type', 'original_name', 'path', 'size', 'uploaded_by'];

    public function project()
    {
        return $this->belongsTo(TebProject::class, 'teb_project_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }
}
