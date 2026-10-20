<?php
// app/Models/Masterlist/EmployeeClass.php
namespace App\Models\Masterlist;

use Illuminate\Database\Eloquent\Model;

class EmployeeClass extends Model
{
    protected $connection = 'recruitment_new';
    protected $table = 'employee_class';
    protected $fillable = ['employid', 'empclass_id'];
    public $timestamps = false;
}