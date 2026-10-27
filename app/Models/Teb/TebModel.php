<?php
// app/Models/Teb/TebModel.php

namespace App\Models\Teb;

use Illuminate\Database\Eloquent\Model;

// Base for the teb_* tables so the connection can be moved with TEB_DB_CONNECTION.
abstract class TebModel extends Model
{
    public function getConnectionName()
    {
        return config('teb.connection') ?: parent::getConnectionName();
    }
}
