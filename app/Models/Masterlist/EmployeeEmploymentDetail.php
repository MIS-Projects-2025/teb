<?php
// app/Models/Masterlist/EmployeeEmploymentDetail.php
namespace App\Models\Masterlist;

use Illuminate\Database\Eloquent\Model;

class EmployeeEmploymentDetail extends Model
{
    protected $connection = 'recruitment_new';
    protected $table = 'employee_employment_details';
    protected $fillable = ['employid', 'date_hired', 'date_reg', 'service_length'];
    public $timestamps = false;

    protected $casts = [
        'date_hired' => 'date',
        'date_reg' => 'date',
    ];
}