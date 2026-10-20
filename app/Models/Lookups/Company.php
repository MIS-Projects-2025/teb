<?php

namespace App\Models\Lookups;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $connection = 'recruitment_new';

    protected $table = 'companies';

    protected $fillable = ['company_name'];

    public $timestamps = false;
}