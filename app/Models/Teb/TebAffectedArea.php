<?php
// app/Models/Teb/TebAffectedArea.php

namespace App\Models\Teb;

class TebAffectedArea extends TebModel
{
    protected $table = 'teb_affected_areas';
    protected $fillable = ['teb_project_id', 'department_id', 'dep_name'];
    public $timestamps = false;

    public function project()
    {
        return $this->belongsTo(TebProject::class, 'teb_project_id');
    }

    public function department()
    {
        return $this->belongsTo(\App\Models\Lookups\Department::class, 'department_id');
    }
}
