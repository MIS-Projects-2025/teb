<?php
// database/migrations/2026_10_26_000001_add_form_fields_to_teb_four_mcns_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 4MCN form CS-027 (Rev.2) "4M Change Application/Notification to ADGT".
// before_change = Current Status, after_change = Change Description, reason = Justification.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teb_four_mcns', function (Blueprint $table) {
            $table->string('title')->nullable()->after('mcn_no');
            $table->string('classification', 10)->nullable()->after('change_items');
            $table->json('types_of_change')->nullable()->after('classification');
            $table->date('date_written')->nullable()->after('types_of_change');
            $table->date('expiration_date')->nullable()->after('effectivity_date');
            $table->unsignedTinyInteger('extension_no')->nullable()->after('expiration_date');
            $table->text('attachments_note')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('teb_four_mcns', function (Blueprint $table) {
            $table->dropColumn(['title', 'classification', 'types_of_change', 'date_written', 'expiration_date', 'extension_no', 'attachments_note']);
        });
    }
};
