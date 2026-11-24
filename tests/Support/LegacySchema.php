<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Minimal copies of the masterlist / qdn tables the TRB portal reads. Sandbox only.
class LegacySchema
{
    public static function create(): void
    {
        $m = Schema::connection('masterlist');
        foreach (['employee_details', 'department', 'employee_department', 'job_level', 'employee_job_level', 'position_level', 'employee_position_level', 'admins'] as $t) {
            $m->dropIfExists($t);
        }
        $m->create('employee_details', function (Blueprint $t) {
            $t->integer('employid')->primary();
            $t->string('firstname');
            $t->string('lastname');
            $t->string('email')->nullable();
            $t->integer('accstatus')->default(1);
        });
        $m->create('department', function (Blueprint $t) {
            $t->id();
            $t->string('dep_name');
        });
        $m->create('employee_department', function (Blueprint $t) {
            $t->id();
            $t->integer('employid');
            $t->integer('department_id');
        });
        $m->create('job_level', function (Blueprint $t) {
            $t->id();
            $t->string('emp_job_level_name');
        });
        $m->create('employee_job_level', function (Blueprint $t) {
            $t->id();
            $t->integer('employid');
            $t->integer('job_level_id');
        });
        $m->create('position_level', function (Blueprint $t) {
            $t->id();
            $t->string('position_level_name');
        });
        $m->create('employee_position_level', function (Blueprint $t) {
            $t->id();
            $t->integer('employid');
            $t->integer('position_level_id');
        });
        $m->create('admins', function (Blueprint $t) {
            $t->id();
            $t->string('employid');
            $t->string('role');
            $t->timestamps();
        });

        // Authify sessions (AuthMiddleware).
        $a = Schema::connection('authify');
        $a->dropIfExists('authify_sessions');
        $a->create('authify_sessions', function (Blueprint $t) {
            $t->string('token')->primary();
            $t->string('portal_id');
            $t->string('emp_id');
            $t->string('emp_name');
            $t->string('emp_firstname')->nullable();
            $t->string('emp_jobtitle')->nullable();
            $t->string('emp_dept')->nullable();
            $t->string('emp_prodline')->nullable();
            $t->string('emp_station')->nullable();
            $t->string('emp_position')->nullable();
            $t->string('emp_job_level')->nullable();
            $t->string('emp_position_level')->nullable();
            $t->string('emp_from')->nullable();
            $t->timestamp('generated_at')->nullable();
            $t->timestamp('pending_logout_at')->nullable();
        });

        $e = Schema::connection('email');
        $e->dropIfExists('email_list');
        $e->dropIfExists('email_accounts');
        $e->create('email_accounts', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('department')->nullable();
            $t->boolean('is_generic')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        $q = Schema::connection('qdn');
        $q->dropIfExists('customer_list');
        $q->create('customer_list', function (Blueprint $t) {
            $t->id();
            $t->string('customer_name');
        });
    }

    public static function seed(): array
    {
        $ml = DB::connection('masterlist');
        $depts = ['Production', 'Process Engineering', 'Equipment Engineering', 'Quality Assurance', 'Quality MAnagement System', 'Facilities', 'Warehouse'];
        foreach ($depts as $d) {
            $ml->table('department')->insert(['dep_name' => $d]);
        }
        $deptId = fn($n) => $ml->table('department')->where('dep_name', $n)->value('id');

        $people = [
            // employid, first, last, username, dept
            [1001, 'Ana', 'Reyes', 'areyes', 'Process Engineering'],     // originator
            [1002, 'Ben', 'Cruz', 'bcruz', 'Process Engineering'],       // TRB admin
        ];
        $levels = ['supervisor' => 'Sup', 'section_head' => 'Head', 'manager' => 'Mgr'];
        $id = 2000;
        $matrix = [];
        foreach (['Production', 'Process Engineering', 'Equipment Engineering', 'Quality Assurance', 'Quality MAnagement System', 'Facilities'] as $d) {
            foreach ($levels as $lvl => $tag) {
                $id++;
                $people[] = [$id, $tag, str_replace(' ', '', $d), strtolower($tag) . $id, $d];
                $matrix[] = [$deptId($d), $lvl, (string) $id];
            }
        }

        // Same ids as the real masterlist: 2 Supervisor, 3 Section Head, 4 Manager, 6 President (5 Operation Director is matched by name).
        foreach (['Engineer', 'Supervisor', 'Section Head', 'Manager', 'Operation Director', 'President'] as $jl) {
            $ml->table('job_level')->insert(['emp_job_level_name' => $jl]);
        }
        $jobLevelId = fn($n) => $ml->table('job_level')->where('emp_job_level_name', $n)->value('id');

        foreach ($people as [$eid, $f, $l, $u, $d]) {
            DB::connection('authify')->table('authify_sessions')->insert([
                'token' => "tok-$eid",
                'portal_id' => config('services.authify.portal_id'),
                'emp_id' => (string) $eid,
                'emp_name' => "$f $l",
                'emp_firstname' => $f,
                'emp_dept' => $d,
                'generated_at' => now(),
            ]);
            $ml->table('employee_details')->insert(['employid' => $eid, 'firstname' => $f, 'lastname' => $l, 'email' => "$u@example.test"]);
            $ml->table('employee_department')->insert(['employid' => $eid, 'department_id' => $deptId($d)]);
            $jobLevel = ['Sup' => 'Supervisor', 'Head' => 'Section Head', 'Mgr' => 'Manager'][$f] ?? 'Engineer';
            $ml->table('employee_job_level')->insert(['employid' => $eid, 'job_level_id' => $jobLevelId($jobLevel)]);
        }

        // Warehouse lead: job level says Engineer, position level says Section Head.
        // position_level ids as in the real masterlist where it matters: 35 Executive (Operation Director and President).
        $ml->table('position_level')->insert(['id' => 1, 'position_level_name' => 'Section Head']);
        $ml->table('position_level')->insert(['id' => 35, 'position_level_name' => 'Executive']);
        $ml->table('employee_details')->insert(['employid' => 3001, 'firstname' => 'Wally', 'lastname' => 'Lead', 'email' => 'wlead@example.test']);
        $ml->table('employee_department')->insert(['employid' => 3001, 'department_id' => $deptId('Warehouse')]);
        $ml->table('employee_job_level')->insert(['employid' => 3001, 'job_level_id' => $jobLevelId('Engineer')]);
        $ml->table('employee_position_level')->insert(['employid' => 3001, 'position_level_id' => 1]);

        // Operation Director (Warehouse): position level 35 Executive, job level "Operation Director". Not a department approval level.
        $ml->table('employee_details')->insert(['employid' => 3002, 'firstname' => 'Dora', 'lastname' => 'Director', 'email' => 'dora@example.test']);
        $ml->table('employee_department')->insert(['employid' => 3002, 'department_id' => $deptId('Warehouse')]);
        $ml->table('employee_job_level')->insert(['employid' => 3002, 'job_level_id' => $jobLevelId('Operation Director')]);
        $ml->table('employee_position_level')->insert(['employid' => 3002, 'position_level_id' => 35]);

        // Company President: position level 35 Executive, job level 6 President, no department needed.
        $ml->table('employee_details')->insert(['employid' => 3003, 'firstname' => 'Pia', 'lastname' => 'President', 'email' => 'pia@example.test']);
        $ml->table('employee_job_level')->insert(['employid' => 3003, 'job_level_id' => $jobLevelId('President')]);
        $ml->table('employee_position_level')->insert(['employid' => 3003, 'position_level_id' => 35]);

        // Job level President but not position level 35 Executive: cannot sign as President.
        $ml->table('employee_details')->insert(['employid' => 3004, 'firstname' => 'Victor', 'lastname' => 'Vice', 'email' => 'victor@example.test']);
        $ml->table('employee_job_level')->insert(['employid' => 3004, 'job_level_id' => $jobLevelId('President')]);
        foreach ([[3002, 'Dora Director', 'Warehouse'], [3003, 'Pia President', 'Management']] as [$eid, $name, $d]) {
            DB::connection('authify')->table('authify_sessions')->insert([
                'token' => "tok-$eid",
                'portal_id' => config('services.authify.portal_id'),
                'emp_id' => (string) $eid,
                'emp_name' => $name,
                'emp_firstname' => explode(' ', $name)[0],
                'emp_dept' => $d,
                'generated_at' => now(),
            ]);
        }

        DB::connection('email')->table('email_accounts')->insert([
            ['email' => 'ben.cruz@example.test', 'name' => 'Ben Cruz', 'department' => 'Process Engineering', 'is_active' => 1],
            ['email' => 'pe.sup@example.test', 'name' => 'PE Supervisor', 'department' => 'Process Engineering', 'is_active' => 1],
            ['email' => 'prod.sup@example.test', 'name' => 'Production Supervisor', 'department' => 'Production', 'is_active' => 1],
            ['email' => 'left@example.test', 'name' => 'Former Employee', 'department' => 'Production', 'is_active' => 0],
        ]);

        foreach (['Acme Semicon', 'Northwind Devices', 'Globex Optics'] as $c) {
            DB::connection('qdn')->table('customer_list')->insert(['customer_name' => $c]);
        }

        return ['matrix' => $matrix, 'dept' => $deptId];
    }
}
