<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rostering (Phase 5b, D68, D72, D73): shift templates, rotation patterns, roster shifts on
 * branch-local dates (an employee or open; draft until published), claims on open shifts and
 * coverage targets per branch weekday.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_templates', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->string('code', 32);
            $table->jsonb('name');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->unsignedTinyInteger('color')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['branch_id', 'is_active']);
        });

        Schema::create('rotation_patterns', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->jsonb('name');
            // One entry per day of the cycle: a shift template id, or null for a day off.
            $table->jsonb('days');
            $table->timestampsTz();
        });

        Schema::create('roster_shifts', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('employee_id')->nullable()->constrained('employees');
            $table->foreignId('shift_template_id')->nullable()->constrained('shift_templates')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->date('date');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->string('status', 12);
            $table->timestampTz('published_at')->nullable();
            $table->boolean('auto_assign')->default(false);
            $table->string('note', 300)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['branch_id', 'date', 'status']);
            $table->index(['employee_id', 'date']);
        });

        Schema::create('open_shift_claims', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('roster_shift_id')->constrained('roster_shifts')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees');
            $table->string('status', 12);
            $table->timestampsTz();

            $table->unique(['roster_shift_id', 'employee_id']);
        });

        Schema::create('coverage_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->unsignedSmallInteger('required');
            $table->timestampsTz();

            $table->unique(['branch_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coverage_targets');
        Schema::dropIfExists('open_shift_claims');
        Schema::dropIfExists('roster_shifts');
        Schema::dropIfExists('rotation_patterns');
        Schema::dropIfExists('shift_templates');
    }
};
