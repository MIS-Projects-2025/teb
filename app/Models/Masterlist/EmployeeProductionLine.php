<?php
// app/Models/Masterlist/EmployeeProductionLine.php
namespace App\Models\Masterlist;

use Illuminate\Database\Eloquent\Model;

class EmployeeProductionLine extends Model
{
    protected $connection = 'recruitment_new';
    protected $table = 'employee_production_line';
    protected $fillable = ['employid', 'prodline_id'];
    public $timestamps = false;
}