<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The register form no longer asks for a Category: the BMP Alignment categories replace it.
// Older entries keep the category they were registered with.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teb_projects', function (Blueprint $table) {
            $table->string('category', 40)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('teb_projects', function (Blueprint $table) {
            $table->string('category', 40)->nullable(false)->default('')->change();
        });
    }
};
