<?php
// database/migrations/2026_10_28_000002_add_charter_to_teb_projects_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Step 1 Project Charter (typed fields, TebProject::CHARTER_FIELDS); the charter document itself is an attachment.
return new class extends Migration
{
    public function getConnection()
    {
        return config('teb.connection') ?: parent::getConnection();
    }

    public function up(): void
    {
        Schema::table('teb_projects', function (Blueprint $table) {
            $table->json('charter')->nullable()->after('change_description');
        });
    }

    public function down(): void
    {
        Schema::table('teb_projects', function (Blueprint $table) {
            $table->dropColumn('charter');
        });
    }
};
