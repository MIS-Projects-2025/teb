<?php
// database/migrations/2026_11_04_000003_add_customer_ids_to_teb_projects_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Several customers per entry: customer_ids keeps the picks; customer_name keeps the names joined with ", "
// (lists, search, export, mails); customer_id keeps the first pick.
return new class extends Migration
{
    public function getConnection()
    {
        return config('teb.connection') ?: parent::getConnection();
    }

    public function up(): void
    {
        Schema::table('teb_projects', function (Blueprint $table) {
            $table->json('customer_ids')->nullable()->after('customer_id');
            $table->text('customer_name')->nullable()->change();
        });

        $db = DB::connection($this->getConnection());
        $db->table('teb_projects')->whereNotNull('customer_id')->where('customer_id', '<>', '')->orderBy('id')
            ->each(fn ($p) => $db->table('teb_projects')->where('id', $p->id)->update(['customer_ids' => json_encode([(string) $p->customer_id])]));
    }

    public function down(): void
    {
        Schema::table('teb_projects', function (Blueprint $table) {
            $table->dropColumn('customer_ids');
        });
    }
};
