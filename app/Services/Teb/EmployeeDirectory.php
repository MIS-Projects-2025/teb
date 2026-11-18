<?php
// app/Services/Teb/EmployeeDirectory.php

namespace App\Services\Teb;

use Illuminate\Support\Facades\DB;

// Active masterlist employees for the Project Charter's Members and Sponsor pickers. Read only.
class EmployeeDirectory
{
    // [['id' => employid, 'name' => 'Last, First · Department'], ...] A-Z.
    public static function options(): array
    {
        return once(fn() => DB::connection('masterlist')->table('employee_details as e')
            ->leftJoin('employee_department as ed', 'ed.employid', '=', 'e.employid')
            ->leftJoin('department as d', 'd.id', '=', 'ed.department_id')
            ->where('e.accstatus', 1)
            ->orderBy('e.lastname')->orderBy('e.firstname')
            ->get(['e.employid', 'e.firstname', 'e.lastname', 'd.dep_name'])
            ->unique('employid')
            ->map(fn($r) => [
                'id'   => (string) $r->employid,
                'name' => trim("{$r->lastname}, {$r->firstname}", ', ') . ($r->dep_name ? " · {$r->dep_name}" : ''),
            ])->values()->all());
    }

    // Plain names ("First Last") of the given employee ids, in the order given; unknown ids are left out.
    public static function names(array $ids): array
    {
        $byId = DB::connection('masterlist')->table('employee_details')->whereIn('employid', $ids)
            ->get(['employid', 'firstname', 'lastname'])->keyBy(fn($r) => (string) $r->employid);

        return collect($ids)->map(fn($id) => $byId->get((string) $id))->filter()
            ->map(fn($r) => trim("{$r->firstname} {$r->lastname}"))->values()->all();
    }
}
