<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Policy engine (ADR-0010): the leave-type catalogue (D48), policy sets with effective-dated
 * values and assignments (never updated in place), and shared holiday calendars assigned to
 * branches (D47).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('code', 32)->unique();
            $table->jsonb('name');
            $table->string('unit', 8)->default('days');
            $table->boolean('is_paid')->default(true);
            $table->boolean('allows_half_days')->default(true);
            $table->string('eligible_gender', 8)->nullable();
            $table->boolean('requires_attachment')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('policy_sets', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->jsonb('name');
            $table->jsonb('description')->nullable();
            $table->string('preset_key', 64)->nullable();
            $table->string('preset_version', 16)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('policy_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('policy_set_id')->constrained('policy_sets')->cascadeOnDelete();
            $table->string('key', 96);
            $table->jsonb('value');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('reason', 300)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->nullable();

            $table->index(['policy_set_id', 'key', 'effective_from']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE policy_values ADD CONSTRAINT policy_values_no_overlap
            EXCLUDE USING gist (policy_set_id WITH =, key WITH =, daterange(effective_from, effective_to, '[]') WITH &&)
            SQL);

        Schema::create('policy_assignments', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('policy_set_id')->constrained('policy_sets')->restrictOnDelete();
            $table->string('level', 16);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('reason', 300)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['level', 'target_id', 'effective_from']);
            $table->index('policy_set_id');
        });

        // One set per target at a time (global has no target).
        DB::statement(<<<'SQL'
            ALTER TABLE policy_assignments ADD CONSTRAINT policy_assignments_no_overlap
            EXCLUDE USING gist (level WITH =, (coalesce(target_id, 0)) WITH =, daterange(effective_from, effective_to, '[]') WITH &&)
            SQL);

        Schema::create('holiday_calendars', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('code', 32)->unique();
            $table->jsonb('name');
            $table->char('country_code', 2)->nullable();
            $table->string('preset_key', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('holiday_rules', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('holiday_calendar_id')->constrained('holiday_calendars')->cascadeOnDelete();
            $table->jsonb('name');
            $table->string('type', 16);
            $table->unsignedSmallInteger('month')->nullable();
            $table->unsignedSmallInteger('day')->nullable();
            $table->unsignedSmallInteger('weekday')->nullable();
            $table->smallInteger('nth')->nullable();
            $table->date('date')->nullable();
            $table->unsignedSmallInteger('duration_days')->default(1);
            $table->string('observed', 24)->default('none');
            $table->boolean('hijri_based')->default(false);
            $table->unsignedSmallInteger('from_year')->nullable();
            $table->unsignedSmallInteger('to_year')->nullable();
            $table->timestampsTz();

            $table->index(['holiday_calendar_id', 'type']);
            $table->index('date');
        });

        Schema::create('branch_holiday_calendar', function (Blueprint $table): void {
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('holiday_calendar_id')->constrained('holiday_calendars')->cascadeOnDelete();
            $table->primary(['branch_id', 'holiday_calendar_id']);
            $table->index('holiday_calendar_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_holiday_calendar');
        Schema::dropIfExists('holiday_rules');
        Schema::dropIfExists('holiday_calendars');
        Schema::dropIfExists('policy_assignments');
        Schema::dropIfExists('policy_values');
        Schema::dropIfExists('policy_sets');
        Schema::dropIfExists('leave_types');
    }
};
