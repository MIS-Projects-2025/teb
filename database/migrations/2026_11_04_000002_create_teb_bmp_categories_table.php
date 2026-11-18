<?php
// database/migrations/2026_11_04_000002_create_teb_bmp_categories_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// BMP Alignment categories, kept on the Settings page (BMP maintenance). Starts with the list in config teb.bmp_categories.
return new class extends Migration
{
    public function getConnection()
    {
        return config('teb.connection') ?: parent::getConnection();
    }

    public function up(): void
    {
        Schema::create('teb_bmp_categories', function (Blueprint $table) {
            $table->id();
            $table->string('driver', 5)->index();          // T / Q / R / D / C (config teb.bmp_drivers)
            $table->string('name', 150);
            $table->string('classification', 10);          // major / minor
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);      // inactive: not offered on new entries, kept on old ones
            $table->timestamps();
            $table->unique(['driver', 'name']);
        });

        $now = now();
        $rows = [];
        foreach (config('teb.bmp_categories', []) as $driver => $categories) {
            $sort = 0;
            foreach ($categories as $name => $classification) {
                $rows[] = ['driver' => $driver, 'name' => $name, 'classification' => $classification, 'sort' => ++$sort,
                    'active' => true, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        DB::connection($this->getConnection())->table('teb_bmp_categories')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('teb_bmp_categories');
    }
};
