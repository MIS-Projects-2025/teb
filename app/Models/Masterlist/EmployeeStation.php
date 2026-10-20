<?php
// app/Models/Masterlist/EmployeeStation.php
namespace App\Models\Masterlist;

use Illuminate\Database\Eloquent\Model;

class EmployeeStation extends Model
{
    protected $connection = 'recruitment_new';
    protected $table = 'employee_station';
    protected $fillable = ['employid', 'station_id'];
    public $timestamps = false;
}