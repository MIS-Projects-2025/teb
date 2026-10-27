<?php
// app/Models/Qdn/Customer.php

namespace App\Models\Qdn;

use Illuminate\Database\Eloquent\Model;

// qdn_db.customer_list — read only, used for the TEB "Customer" dropdown.
class Customer extends Model
{
    public $timestamps = false;
    protected $guarded = ['*'];

    public function getConnectionName()
    {
        return config('teb.customer.connection');
    }

    public function getTable()
    {
        return config('teb.customer.table');
    }

    public function getKeyName()
    {
        return config('teb.customer.key_column');
    }

    public function getDisplayNameAttribute(): string
    {
        return (string) $this->getAttribute(config('teb.customer.name_column'));
    }

    // [id => name] for dropdowns.
    public static function options(): array
    {
        $name = config('teb.customer.name_column');

        return static::query()->orderBy($name)->pluck($name, config('teb.customer.key_column'))->all();
    }
}
