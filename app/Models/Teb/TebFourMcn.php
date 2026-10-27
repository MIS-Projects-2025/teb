<?php
// app/Models/Teb/TebFourMcn.php

namespace App\Models\Teb;

// 4M Change Application/Notification to ADGT (CS-027), generated after approval when the TEB needs one.
// before_change = Current Status, after_change = Change Description, reason = Justification.
class TebFourMcn extends TebModel
{
    protected $table = 'teb_four_mcns';

    protected $fillable = [
        'teb_project_id',
        'mcn_no',
        'title',
        'change_items',
        'classification',
        'types_of_change',
        'date_written',
        'before_change',
        'after_change',
        'reason',
        'attachments_note',
        'effectivity_date',
        'expiration_date',
        'extension_no',
        'customer_notification',
        'prepared_by',
        'prepared_name',
    ];

    protected $casts = [
        'change_items'          => 'array',
        'types_of_change'       => 'array',
        'date_written'          => 'date',
        'effectivity_date'      => 'date',
        'expiration_date'       => 'date',
        'extension_no'          => 'integer',
        'customer_notification' => 'boolean',
    ];

    public function project()
    {
        return $this->belongsTo(TebProject::class, 'teb_project_id');
    }

    public function getChangeItemsLabelAttribute(): string
    {
        $labels = config('teb.4m_items');

        return collect($this->change_items ?? [])->map(fn ($i) => $labels[$i] ?? $i)->implode(', ');
    }

    // 4MCN-yyyy-xxxxx
    public static function nextNo(): string
    {
        $year = now()->format('Y');
        $last = static::where('mcn_no', 'like', "4MCN-{$year}-%")->orderByDesc('mcn_no')->value('mcn_no');
        $seq  = $last ? ((int) substr($last, -5)) + 1 : 1;

        return sprintf('4MCN-%s-%05d', $year, $seq);
    }
}
