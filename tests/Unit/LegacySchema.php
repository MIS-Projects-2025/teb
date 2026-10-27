<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

// Minimal copies of the masterlist / qdn tables the TEB portal reads. Sandbox only.
class LegacySchema
{
    public static function create(): void
    {
        $m = Schema::connection('masterlist');
        foreach (['login', 'employee_details', 'department', 'employee_department', 'admins'] as $t) {
            $m->dropIfExists($t);
        }
        $m->create('login', function (Blueprint $t) {
            $t->string('EMPLOYID')->primary();
            $t->string('EMPNAME'); $t->string('USERNAME'); $t->string('PASSWRD');
            $t->integer('ACCSTATUS')->default(1); $t->string('ROLE')->nullable();
        });
        $m->create('employee_details', function (Blueprint $t) {
            $t->integer('employid')->primary();
            $t->string('firstname'); $t->string('lastname'); $t->string('email')->nullable();
            $t->integer('accstatus')->default(1);
        });
        $m->create('department', function (Blueprint $t) { $t->id(); $t->string('dep_name'); });
        $m->create('employee_department', function (Blueprint $t) { $t->id(); $t->integer('employid'); $t->integer('department_id'); });
        $m->create('admins', function (Blueprint $t) { $t->id(); $t->string('employid'); $t->string('role'); $t->timestamps(); });

        $q = Schema::connection('qdn');
        $q->dropIfExists('customer_list');
        $q->create('customer_list', function (Blueprint $t) { $t->id(); $t->string('customer_name'); });
    }

    public static function seed(): array
    {
        $ml = DB::connection('masterlist');
        $depts = ['Production', 'Process Engineering', 'Equipment Engineering', 'Quality Assurance', 'Facilities', 'Warehouse'];
        foreach ($depts as $d) {
            $ml->table('department')->insert(['dep_name' => $d]);
        }
        $deptId = fn ($n) => $ml->table('department')->where('dep_name', $n)->value('id');

        $people = [
            // employid, first, last, username, dept
            [1001, 'Ana', 'Reyes', 'areyes', 'Process Engineering'],     // originator
            [1002, 'Ben', 'Cruz', 'bcruz', 'Process Engineering'],       // TEB admin
        ];
        $levels = ['supervisor' => 'Sup', 'section_head' => 'Head', 'manager' => 'Mgr'];
        $id = 2000;
        $matrix = [];
        foreach (['Production', 'Process Engineering', 'Equipment Engineering', 'Quality Assurance', 'Facilities'] as $d) {
            foreach ($levels as $lvl => $tag) {
                $id++;
                $people[] = [$id, $tag, str_replace(' ', '', $d), strtolower($tag).$id, $d];
                $matrix[] = [$deptId($d), $lvl, (string) $id];
            }
        }

        foreach ($people as [$eid, $f, $l, $u, $d]) {
            $ml->table('login')->insert(['EMPLOYID' => (string) $eid, 'EMPNAME' => "$f $l", 'USERNAME' => $u, 'PASSWRD' => Hash::make('secret'), 'ACCSTATUS' => 1]);
            $ml->table('employee_details')->insert(['employid' => $eid, 'firstname' => $f, 'lastname' => $l, 'email' => "$u@example.test"]);
            $ml->table('employee_department')->insert(['employid' => $eid, 'department_id' => $deptId($d)]);
        }

        foreach (['Acme Semicon', 'Northwind Devices', 'Globex Optics'] as $c) {
            DB::connection('qdn')->table('customer_list')->insert(['customer_name' => $c]);
        }

        return ['matrix' => $matrix, 'dept' => $deptId];
    }
}
