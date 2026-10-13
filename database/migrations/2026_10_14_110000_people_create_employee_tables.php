<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * People (brief §5, architecture "People"): employees with encrypted sensitive fields and HMAC
 * blind indexes, effective-dated assignments (one primary at a time, enforced by an exclusion
 * constraint), the reporting-line closure table, and the branch manager / department head links.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        DB::statement('CREATE SEQUENCE IF NOT EXISTS employee_number_seq START 1');

        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('employee_number', 32)->unique();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();

            $table->string('name_en', 160);
            $table->string('name_ar', 160)->nullable();
            $table->string('preferred_name', 80)->nullable();
            $table->string('work_email', 254)->nullable();
            $table->string('work_phone_e164', 20)->nullable();

            $table->string('gender', 16)->nullable();
            $table->char('nationality', 2)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('marital_status', 16)->nullable();

            // Sensitive (ADR-0007): ciphertext + blind index for exact-match lookups.
            $table->text('national_id')->nullable();
            $table->char('national_id_bidx', 64)->nullable()->index();
            $table->text('passport_number')->nullable();
            $table->char('passport_bidx', 64)->nullable()->index();
            $table->char('passport_country', 2)->nullable();
            $table->date('passport_expires_on')->nullable();
            $table->text('residency_number')->nullable();
            $table->char('residency_bidx', 64)->nullable()->index();
            $table->date('residency_expires_on')->nullable();
            $table->text('bank_details')->nullable();
            $table->text('personal_contact')->nullable();

            $table->string('status', 16)->default('active');
            $table->date('hire_date');
            $table->date('probation_ends_on')->nullable();
            $table->date('termination_date')->nullable();
            $table->string('termination_reason', 300)->nullable();

            // Denormalised from the current primary assignment for list filters and scope checks.
            $table->foreignId('current_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('current_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('current_position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->unsignedBigInteger('current_manager_id')->nullable();
            $table->foreignId('current_employment_type_id')->nullable()->constrained('employment_types')->nullOnDelete();
            $table->foreignId('current_grade_id')->nullable()->constrained('grades')->nullOnDelete();

            $table->text('search_text')->default('');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['current_branch_id', 'status']);
            $table->index(['current_department_id', 'status']);
            $table->index('current_manager_id');
            $table->index(['status', 'name_en']);
        });

        Schema::table('employees', function (Blueprint $table): void {
            $table->foreign('current_manager_id')->references('id')->on('employees')->nullOnDelete();
        });

        DB::statement('CREATE UNIQUE INDEX employees_work_email_unique ON employees (lower(work_email)) WHERE work_email IS NOT NULL');
        DB::statement('CREATE INDEX employees_search_text_trgm ON employees USING gin (search_text gin_trgm_ops)');

        Schema::create('employee_assignments', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->boolean('is_primary')->default(true);
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('manager_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('employment_type_id')->nullable()->constrained('employment_types')->nullOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained('grades')->nullOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('reason', 300)->nullable();
            // In force on the branch-local date; maintained by SyncEmployeePlacement and the daily job.
            $table->boolean('is_current')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['employee_id', 'effective_from']);
            $table->index(['branch_id', 'effective_from']);
            $table->index(['department_id', 'effective_from']);
            $table->index('manager_employee_id');
            $table->index(['is_current', 'branch_id']);
            $table->index(['is_current', 'department_id']);
        });

        DB::statement('ALTER TABLE employee_assignments ADD CONSTRAINT employee_assignments_dates_check CHECK (effective_to IS NULL OR effective_to >= effective_from)');
        DB::statement(<<<'SQL'
            ALTER TABLE employee_assignments ADD CONSTRAINT employee_assignments_one_primary
            EXCLUDE USING gist (employee_id WITH =, daterange(effective_from, effective_to, '[]') WITH &&)
            WHERE (is_primary)
            SQL);

        Schema::create('employee_reporting_paths', function (Blueprint $table): void {
            $table->foreignId('ancestor_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('descendant_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedSmallInteger('depth');
            $table->primary(['ancestor_id', 'descendant_id']);
            $table->index(['descendant_id', 'depth']);
        });

        Schema::table('branches', function (Blueprint $table): void {
            $table->foreign('manager_employee_id')->references('id')->on('employees')->nullOnDelete();
        });

        Schema::table('departments', function (Blueprint $table): void {
            $table->foreign('head_employee_id')->references('id')->on('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('departments', fn (Blueprint $table) => $table->dropForeign(['head_employee_id']));
        Schema::table('branches', fn (Blueprint $table) => $table->dropForeign(['manager_employee_id']));
        Schema::dropIfExists('employee_reporting_paths');
        Schema::dropIfExists('employee_assignments');
        Schema::dropIfExists('employees');
        DB::statement('DROP SEQUENCE IF EXISTS employee_number_seq');
    }
};
