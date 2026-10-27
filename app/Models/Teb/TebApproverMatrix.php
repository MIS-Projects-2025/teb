<?php
// app/Models/Teb/TebApproverMatrix.php

namespace App\Models\Teb;

use App\Models\Lookups\Department;
use App\Models\Masterlist\Employee;

// Who signs for a department at each level. Maintained by the TEB Admin.
class TebApproverMatrix extends TebModel
{
    protected $table = 'teb_approver_matrix';
    protected $fillable = ['department_id', 'level', 'employid'];

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employid', 'employid');
    }

    public static function approverFor(int $departmentId, string $level): ?string
    {
        return static::where('department_id', $departmentId)->where('level', $level)->value('employid');
    }
}
