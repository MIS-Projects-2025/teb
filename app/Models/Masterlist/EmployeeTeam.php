<?php
// app/Models/Masterlist/EmployeeTeam.php
namespace App\Models\Masterlist;

use Illuminate\Database\Eloquent\Model;

class EmployeeTeam extends Model
{
    protected $connection = 'recruitment_new';
    protected $table = 'employee_team';
    protected $fillable = ['employid', 'team_id'];
    public $timestamps = false;
}