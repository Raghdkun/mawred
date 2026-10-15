<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The leave engine (Phase 5a, D63–D65): an append-only ledger per employee, leave type and
 * calendar year; time away taken through requests (leave, a full day off, hourly permission),
 * where pending rows are the holds; blackout periods; and the link from a request type to the
 * leave type (or hourly-permission quota) it draws on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('employee_id')->constrained('employees');
            $table->string('kind', 12);
            $table->foreignId('leave_type_id')->nullable()->constrained('leave_types');
            $table->foreignId('request_id')->nullable()->unique()->constrained('requests')->nullOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('half_day', 8)->nullable();
            $table->time('from_time')->nullable();
            $table->time('to_time')->nullable();
            $table->unsignedInteger('minutes')->nullable();
            $table->decimal('amount', 8, 2);
            $table->string('status', 12);
            $table->timestampsTz();

            $table->index(['employee_id', 'starts_on', 'ends_on']);
            $table->index(['status', 'starts_on', 'ends_on']);
        });

        Schema::create('leave_ledger', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('leave_type_id')->constrained('leave_types');
            $table->unsignedSmallInteger('year');
            $table->string('kind', 16);
            $table->decimal('amount', 8, 2);
            $table->date('effective_on');
            $table->date('expires_on')->nullable();
            $table->foreignId('leave_request_id')->nullable()->constrained('leave_requests')->nullOnDelete();
            $table->string('note', 300)->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['employee_id', 'leave_type_id', 'year']);
            $table->index(['kind', 'effective_on']);
        });

        Schema::create('leave_blackouts', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->jsonb('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->cascadeOnDelete();
            $table->jsonb('leave_type_ids')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['starts_on', 'ends_on']);
        });

        Schema::table('request_types', function (Blueprint $table): void {
            $table->string('behaviour', 24)->nullable();
            $table->foreignId('leave_type_id')->nullable()->constrained('leave_types')->nullOnDelete();
        });

        // Annual and sick leave were seeded flagged for this phase (D55); they now draw on the
        // leave engine. The day-off type's hourly option draws on the hourly-permission quota.
        foreach (['ANNUAL_LEAVE' => 'ANNUAL', 'SICK_LEAVE' => 'SICK'] as $requestCode => $leaveCode) {
            $leaveTypeId = DB::table('leave_types')->where('code', $leaveCode)->value('id');

            if ($leaveTypeId !== null) {
                DB::table('request_types')->where('code', $requestCode)->update(['behaviour' => 'leave', 'leave_type_id' => $leaveTypeId, 'available_in_phase' => 0]);
            }
        }

        DB::table('request_types')->where('code', 'DAY_OFF')->update(['behaviour' => 'hourly_permission']);
    }

    public function down(): void
    {
        Schema::table('request_types', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('leave_type_id');
            $table->dropColumn('behaviour');
        });
        Schema::dropIfExists('leave_blackouts');
        Schema::dropIfExists('leave_ledger');
        Schema::dropIfExists('leave_requests');
    }
};
