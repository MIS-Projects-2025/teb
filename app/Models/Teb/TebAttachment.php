<?php
// app/Models/Teb/TebAttachment.php

namespace App\Models\Teb;

class TebAttachment extends TebModel
{
    protected $table = 'teb_attachments';

    const TYPE_FMEA       = 'fmea';
    const TYPE_TEB_REPORT = 'teb_report';
    const TYPE_4MCN       = '4mcn';
    const TYPE_OTHER      = 'other';

    const TYPE_LABELS = [
        self::TYPE_FMEA       => 'FMEA',
        self::TYPE_TEB_REPORT => 'TEB Report',
        self::TYPE_4MCN       => '4MCN Support',
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
