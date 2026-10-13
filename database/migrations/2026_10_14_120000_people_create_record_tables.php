<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Employee records around the core profile: custom fields, documents with expiry reminders,
 * employment history (with encrypted salary) and asset assignments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_definitions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('key', 48)->unique();
            $table->jsonb('label');
            $table->jsonb('help')->nullable();
            $table->string('type', 16);
            $table->jsonb('options')->nullable();
            $table->string('section', 16)->default('personal');
            $table->boolean('is_required')->default(false);
            $table->boolean('is_sensitive')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('employee_custom_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('definition_id')->constrained('custom_field_definitions')->cascadeOnDelete();
            $table->jsonb('value')->nullable();
            $table->text('value_encrypted')->nullable();
            $table->timestampsTz();

            $table->unique(['employee_id', 'definition_id']);
            $table->index('definition_id');
        });

        Schema::create('document_types', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('code', 32)->unique();
            $table->jsonb('name');
            $table->boolean('requires_expiry')->default(false);
            $table->jsonb('reminder_days')->default('[30,7]');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('employee_documents', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('document_type_id')->constrained('document_types')->restrictOnDelete();
            $table->text('number')->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('notes', 500)->nullable();
            $table->jsonb('reminders_sent')->default('[]');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['employee_id', 'document_type_id']);
            $table->index('expires_on');
        });

        Schema::create('employment_history', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('event', 32);
            $table->date('effective_on');
            $table->jsonb('changes')->default('{}');
            $table->text('salary_minor')->nullable();
            $table->char('salary_currency', 3)->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['employee_id', 'effective_on']);
            $table->index(['event', 'effective_on']);
        });

        Schema::create('asset_assignments', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('category', 40)->nullable();
            $table->string('asset_tag', 60);
            $table->string('serial_number', 80)->nullable();
            $table->date('assigned_on');
            $table->date('due_back_on')->nullable();
            $table->date('returned_on')->nullable();
            $table->string('condition_notes', 500)->nullable();
            $table->timestampsTz();

            $table->index('employee_id');
        });

        // An asset tag can only be out with one person at a time.
        DB::statement('CREATE UNIQUE INDEX asset_assignments_out_unique ON asset_assignments (upper(asset_tag)) WHERE returned_on IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_assignments');
        Schema::dropIfExists('employment_history');
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('document_types');
        Schema::dropIfExists('employee_custom_values');
        Schema::dropIfExists('custom_field_definitions');
    }
};
