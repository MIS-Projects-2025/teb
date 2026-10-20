<?php

namespace App\Models\Lookups;

use Illuminate\Database\Eloquent\Model;

class PostingTitle extends Model
{
    protected $connection = 'recruitment_new';

    protected $fillable = ['title'];

    public $timestamps = false;
}