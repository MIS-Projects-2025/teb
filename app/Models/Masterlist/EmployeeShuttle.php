<?php
// app/Models/Masterlist/EmployeeShuttle.php
namespace App\Models\Masterlist;

use Illuminate\Database\Eloquent\Model;

class EmployeeShuttle extends Model
{
    protected $connection = 'recruitment_new';
    protected $table = 'employee_shuttle';
    protected $fillable = ['employid', 'shuttle_id'];
    public $timestamps = false;
}