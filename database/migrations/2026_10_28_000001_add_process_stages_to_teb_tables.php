<?php
// database/migrations/2026_10_28_000001_add_process_stages_to_teb_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// TEB process A-C (steps 1-10): the approval section runs twice, once for the trial plan (step 4)
// and once for the results / TSPI (step 6). Existing rows are results approvals.
return new class extends Migration
{
    public function getConnection()
    {
        return config('teb.connection') ?: parent::getConnection();
    }

    public function up(): void
    {
        Schema::table('teb_approvals', function (Blueprint $table) {
            $table->string('round', 10)->default('results')->after('teb_project_id')->index();
        });

        Schema::table('teb_projects', function (Blueprint $table) {
            $table->boolean('customer_approval_required')->nullable()->after('requires_4mcn');
            $table->timestamp('plan_submitted_at')->nullable()->after('teb_no_assigned_at');
            $table->timestamp('implemented_at')->nullable()->after('approved_at');
            $table->timestamp('closed_at')->nullable()->after('implemented_at');
            // Gate records of steps 3 to 10 (App\Services\Teb\TebGates).
            $table->json('gate_records')->nullable()->after('closed_at');
        });
    }

    public function down(): void
    {
        Schema::table('teb_approvals', function (Blueprint $table) {
            $table->dropIndex(['round']);
            $table->dropColumn('round');
        });

        Schema::table('teb_projects', function (Blueprint $table) {
            $table->dropColumn(['customer_approval_required', 'plan_submitted_at', 'implemented_at', 'closed_at', 'gate_records']);
        });
    }
};
