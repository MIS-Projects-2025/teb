<?php
// app/Models/Masterlist/Employee.php

namespace App\Models\Masterlist;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $connection = 'recruitment_new';

    protected $table = 'employee_details';

    protected $primaryKey = 'employid';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'employid',
        'firstname',
        'middlename',
        'lastname',
        'nickname',
        'birthday',
        'place_of_birth',
        'emp_sex',
        'email',
        'contact_no',
        'civil_status',
        'height',
        'weight',
        'blood_type',
        'educational_attainment',
        'accstatus',
        'biometric_status',
        'created_at',
        'religion',
    ];

    protected $casts = [
        'birthday' => 'date',
        'created_at' => 'datetime',
    ];

    public function departments()
    {
        return $this->hasMany(EmployeeDepartment::class, 'employid', 'employid');
    }

    public function jobLevels()
    {
        return $this->hasMany(EmployeeJobLevel::class, 'employid', 'employid');
    }
}