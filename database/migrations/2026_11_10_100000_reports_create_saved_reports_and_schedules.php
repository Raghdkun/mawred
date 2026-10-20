<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Phase 8b: custom reports saved from the builder (D118, D121), shared with people or roles
 * (D119), and schedules that run a prebuilt or saved report for its owner (D120).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_reports', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('description', 300)->nullable();
            $table->string('dataset', 40);
            $table->jsonb('definition');
            $table->timestampsTz();

            $table->index(['owner_user_id', 'updated_at']);
        });

        Schema::create('saved_report_shares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saved_report_id')->constrained('saved_reports')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('roles')->cascadeOnDelete();
            $table->timestampTz('created_at');

            $table->unique(['saved_report_id', 'user_id']);
            $table->unique(['saved_report_id', 'role_id']);
            $table->index('user_id');
            $table->index('role_id');
        });

        Schema::create('report_schedules', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('report', 60);
            $table->jsonb('params');
            $table->string('format', 4);
            $table->string('frequency', 8);
            $table->unsignedSmallInteger('weekday')->nullable();
            $table->unsignedSmallInteger('month_day')->nullable();
            $table->unsignedSmallInteger('hour');
            $table->string('timezone', 64);
            $table->string('locale', 2)->default('en');
            $table->timestampTz('next_run_at');
            $table->timestampTz('last_run_at')->nullable();
            $table->foreignId('last_run_id')->nullable()->constrained('report_runs')->nullOnDelete();
            $table->timestampTz('paused_at')->nullable();
            $table->string('paused_reason', 300)->nullable();
            $table->timestampsTz();

            $table->index(['paused_at', 'next_run_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
        Schema::dropIfExists('saved_report_shares');
        Schema::dropIfExists('saved_reports');
    }
};
