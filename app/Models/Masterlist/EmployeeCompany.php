<?php
// app/Models/Masterlist/EmployeeCompany.php
namespace App\Models\Masterlist;

use Illuminate\Database\Eloquent\Model;

class EmployeeCompany extends Model
{
    protected $connection = 'recruitment_new';
    protected $table = 'employee_company';
    protected $fillable = ['employid', 'company_id'];
    public $timestamps = false;
}