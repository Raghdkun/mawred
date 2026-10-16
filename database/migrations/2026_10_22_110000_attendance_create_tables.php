<?php

declare(strict_types=1);

use App\Support\Database\PartitionManager;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance (Phase 5b, D26, D68, D69): punches (append-only, monthly partitions by the time
 * they happened, ADR-0018), one computed day per employee and branch-local date, and kiosks
 * that show rotating QR codes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosks', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('secret');
            $table->char('token_hash', 64)->unique();
            $table->timestampTz('last_seen_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            CREATE TABLE attendance_events (
                id bigserial,
                ulid char(26) NOT NULL,
                employee_id bigint NOT NULL,
                branch_id bigint NULL,
                kind varchar(8) NOT NULL,
                source varchar(12) NOT NULL,
                occurred_at timestamptz NOT NULL,
                received_at timestamptz NOT NULL,
                local_date date NOT NULL,
                drift_seconds integer NULL,
                latitude numeric(9,6) NULL,
                longitude numeric(9,6) NULL,
                accuracy_m integer NULL,
                geofence varchar(10) NOT NULL,
                flagged boolean NOT NULL DEFAULT false,
                reason varchar(300) NULL,
                kiosk_id bigint NULL,
                voided_at timestamptz NULL,
                actor_user_id bigint NULL,
                created_at timestamptz NOT NULL DEFAULT now(),
                PRIMARY KEY (id, occurred_at)
            ) PARTITION BY RANGE (occurred_at)
            SQL);
        DB::statement('CREATE UNIQUE INDEX attendance_events_ulid_idx ON attendance_events (ulid, occurred_at)');
        DB::statement('CREATE INDEX attendance_events_day_idx ON attendance_events (employee_id, local_date)');
        DB::statement('CREATE INDEX attendance_events_branch_idx ON attendance_events (branch_id, local_date)');
        resolve(PartitionManager::class)->ensureMonthlyPartitions('attendance_events', CarbonImmutable::now()->subMonths(2), 5);

        Schema::create('attendance_days', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->date('date');
            $table->string('schedule_source', 8);
            $table->timestampTz('scheduled_start')->nullable();
            $table->timestampTz('scheduled_end')->nullable();
            $table->unsignedInteger('scheduled_minutes')->default(0);
            $table->timestampTz('first_in')->nullable();
            $table->timestampTz('last_out')->nullable();
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_leave_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->jsonb('overtime_buckets')->nullable();
            $table->string('overtime_status', 10)->default('none');
            $table->unsignedInteger('overtime_approved_minutes')->default(0);
            $table->foreignId('overtime_request_id')->nullable()->constrained('requests')->nullOnDelete();
            $table->string('status', 12);
            $table->jsonb('flags')->nullable();
            $table->boolean('corrected')->default(false);
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['employee_id', 'date']);
            $table->index(['branch_id', 'date', 'status']);
            $table->index(['overtime_status', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_days');
        DB::statement('DROP TABLE IF EXISTS attendance_events CASCADE');
        Schema::dropIfExists('kiosks');
    }
};
