<?php

namespace App\Models\Lookups;

use Illuminate\Database\Eloquent\Model;

class PositionLevel extends Model
{

    protected $connection = 'recruitment_new';

    protected $table = 'position_level';

    // Table has no created_at / updated_at columns
    public $timestamps = false;

    protected $fillable = [
        'position_level_name',
    ];
}