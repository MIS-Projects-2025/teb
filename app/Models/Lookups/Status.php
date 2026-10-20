<?php

namespace App\Models\Lookups;

use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    protected $connection = 'recruitment_new';

    protected $table = 'status';

    protected $fillable = ['status_name'];

    public $timestamps = false;
}