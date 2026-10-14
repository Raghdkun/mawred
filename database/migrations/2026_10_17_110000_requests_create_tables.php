<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Requests (handoff 10–13): request types tie a form to a workflow; a request is one submission
 * running through one workflow instance. Branch and department are snapshotted at submission for
 * scoping and reports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_types', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('code', 40)->unique();
            $table->jsonb('name');
            $table->jsonb('description')->nullable();
            $table->string('category', 24);
            $table->string('icon', 40)->nullable();
            $table->foreignId('form_id')->constrained('forms');
            $table->foreignId('workflow_definition_id')->constrained('workflow_definitions');
            $table->boolean('is_sensitive')->default(false);
            $table->unsignedSmallInteger('available_in_phase')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['is_active', 'category', 'sort_order']);
        });

        DB::statement('CREATE SEQUENCE IF NOT EXISTS request_number_seq');

        Schema::create('requests', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('number', 24)->unique();
            $table->foreignId('request_type_id')->constrained('request_types');
            $table->foreignId('requester_employee_id')->constrained('employees');
            $table->foreignId('requester_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches');
            $table->foreignId('department_id')->nullable()->constrained('departments');
            $table->foreignId('form_submission_id')->constrained('form_submissions');
            $table->foreignId('workflow_instance_id')->nullable()->constrained('workflow_instances')->nullOnDelete();
            $table->string('status', 16);
            $table->timestampTz('submitted_at');
            $table->timestampTz('decided_at')->nullable();
            $table->timestampsTz();

            $table->index(['requester_employee_id', 'submitted_at']);
            $table->index(['status', 'submitted_at']);
            $table->index(['branch_id', 'status', 'submitted_at']);
            $table->index(['request_type_id', 'submitted_at']);
        });

        Schema::create('request_comments', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['request_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_comments');
        Schema::dropIfExists('requests');
        DB::statement('DROP SEQUENCE IF EXISTS request_number_seq');
        Schema::dropIfExists('request_types');
    }
};
