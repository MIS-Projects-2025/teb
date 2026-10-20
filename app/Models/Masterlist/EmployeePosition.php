<?php
// app/Models/Masterlist/EmployeePosition.php
namespace App\Models\Masterlist;

use Illuminate\Database\Eloquent\Model;

class EmployeePosition extends Model
{
    protected $connection = 'recruitment_new';
    protected $table = 'employee_position';
    protected $fillable = ['employid', 'empposition_id'];
    public $timestamps = false;
}