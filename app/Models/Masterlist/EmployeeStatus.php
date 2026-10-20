<?php
// app/Models/Masterlist/EmployeeStatus.php
namespace App\Models\Masterlist;

use Illuminate\Database\Eloquent\Model;

class EmployeeStatus extends Model
{
    protected $connection = 'recruitment_new';
    protected $table = 'employee_status';
    protected $fillable = ['employid', 'empstatus_id'];
    public $timestamps = false;
}