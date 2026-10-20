<?php

namespace App\Models\Lookups;

use Illuminate\Database\Eloquent\Model;

class ClassLookup extends Model
{
    protected $connection = 'recruitment_new';

    protected $table = 'class';

    protected $fillable = ['class_name'];

    public $timestamps = false;
}