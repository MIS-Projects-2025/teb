<?php
// app/Services/Teb/EmailDirectory.php

namespace App\Services\Teb;

use Illuminate\Support\Facades\DB;

// Email accounts offered as reviewees (config teb.email_accounts). Read only.
class EmailDirectory
{
    // Active email addresses, A-Z.
    public static function options(): array
    {
        $cfg    = config('teb.email_accounts');
        $email  = $cfg['email_column'];
        $active = $cfg['active_column'] ?: null;

        return DB::connection($cfg['connection'])->table($cfg['table'])
            ->whereNotNull($email)->where($email, '!=', '')
            ->when($active, fn ($q) => $q->where($active, 1))
            ->orderBy($email)
            ->pluck($email)
            ->map(fn ($e) => trim($e))
            ->unique()->values()->all();
    }
}
