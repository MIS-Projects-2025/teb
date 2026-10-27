<?php
// app/Models/Teb/TebReview.php

namespace App\Models\Teb;

class TebReview extends TebModel
{
    protected $table = 'teb_reviews';

    protected $fillable = [
        'teb_project_id',
        'review_at',
        'duration_minutes',
        'location',
        'reviewees',
        'notes',
        'scheduled_by',
        'invites_sent',
    ];

    protected $casts = [
        'review_at'    => 'datetime',
        'reviewees'    => 'array',
        'duration_minutes' => 'integer',
        'invites_sent' => 'integer',
    ];

    public function project()
    {
        return $this->belongsTo(TebProject::class, 'teb_project_id');
    }
}
