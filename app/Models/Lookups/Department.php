<?php

namespace App\Models\Lookups;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $connection = 'recruitment_new';

    protected $table = 'department';

    protected $fillable = ['dep_name'];

    public $timestamps = false;
}