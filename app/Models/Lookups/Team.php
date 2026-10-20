<?php

namespace App\Models\Lookups;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $connection = 'recruitment_new';

    protected $table = 'teams';

    protected $fillable = ['team_name'];

    public $timestamps = false;
}