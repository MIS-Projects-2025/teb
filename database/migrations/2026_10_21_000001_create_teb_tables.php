<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection()
    {
        return config('teb.connection') ?: parent::getConnection();
    }

    public function up(): void
    {
        Schema::create('teb_projects', function (Blueprint $table) {
            $table->id();
            $table->string('teb_no', 20)->nullable()->unique();
            $table->string('title');
            $table->string('category', 40)->index();
            $table->string('customer_id', 50)->nullable();
            $table->string('customer_name')->nullable();
            $table->string('classification', 10);
            $table->json('types_of_change');
            $table->text('project_description');
            $table->text('current_status')->nullable();
            $table->text('change_description')->nullable();
            $table->string('owner_employid', 20)->index();
            $table->string('owner_name')->nullable();
            $table->string('owner_department')->nullable();
            $table->string('status', 30)->index();
            $table->boolean('requires_4mcn')->nullable();
            $table->text('completion_remarks')->nullable();
            $table->timestamp('teb_no_assigned_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('teb_affected_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teb_project_id')->constrained('teb_projects')->cascadeOnDelete();
            $table->unsignedBigInteger('department_id')->index();
            $table->string('dep_name')->nullable();
        });

        Schema::create('teb_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teb_project_id')->constrained('teb_projects')->cascadeOnDelete();
            $table->string('type', 20)->index();
            $table->string('original_name');
            $table->string('path');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('uploaded_by', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('teb_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teb_project_id')->constrained('teb_projects')->cascadeOnDelete();
            $table->dateTime('review_at');
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->string('location')->nullable();
            $table->json('reviewees')->nullable();
            $table->text('notes')->nullable();
            $table->string('scheduled_by', 20)->nullable();
            $table->unsignedSmallInteger('invites_sent')->default(0);
            $table->timestamps();
        });

        Schema::create('teb_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teb_project_id')->constrained('teb_projects')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('remarks')->nullable();
            $table->string('employid', 20)->nullable();
            $table->string('emp_name')->nullable();
            $table->timestamps();
        });

        Schema::create('teb_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teb_project_id')->constrained('teb_projects')->cascadeOnDelete();
            $table->string('department_group');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('dep_name')->nullable();
            $table->unsignedSmallInteger('department_order')->default(0);
            $table->string('level', 20);
            $table->unsignedTinyInteger('level_order');
            $table->string('approver_employid', 20)->nullable()->index();
            $table->string('approver_name')->nullable();
            $table->string('status', 10)->default('pending')->index();
            $table->text('remarks')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('teb_approver_matrix', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id');
            $table->string('level', 20);
            $table->string('employid', 20);
            $table->timestamps();
            $table->unique(['department_id', 'level']);
        });

        Schema::create('teb_admins', function (Blueprint $table) {
            $table->id();
            $table->string('employid', 20)->unique();
            $table->timestamps();
        });

        Schema::create('teb_four_mcns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teb_project_id')->unique()->constrained('teb_projects')->cascadeOnDelete();
            $table->string('mcn_no', 20)->unique();
            $table->json('change_items');
            $table->text('before_change');
            $table->text('after_change');
            $table->text('reason');
            $table->date('effectivity_date');
            $table->boolean('customer_notification')->default(false);
            $table->string('prepared_by', 20)->nullable();
            $table->string('prepared_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['teb_four_mcns', 'teb_admins', 'teb_approver_matrix', 'teb_approvals', 'teb_status_logs',
                  'teb_reviews', 'teb_attachments', 'teb_affected_areas', 'teb_projects'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
