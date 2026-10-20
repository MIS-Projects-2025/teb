<?php

namespace App\Models\Lookups;

use Illuminate\Database\Eloquent\Model;

class Station extends Model
{
    protected $connection = 'recruitment_new';

    protected $fillable = ['station_name'];

    public $timestamps = false;
}