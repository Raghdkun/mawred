<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The internal calendar (Phase 5b-2, D75–D78): events with participants and their replies,
 * simple repeats (a rule on the series, skipped dates, and overrides that replace one occurrence),
 * and booking pages people book slots from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_pages', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->jsonb('title');
            $table->jsonb('description')->nullable();
            $table->string('location', 200)->nullable();
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('buffer_minutes')->default(0);
            $table->jsonb('windows');
            $table->string('timezone', 64);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('audience', 12)->default('everyone');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['is_active', 'audience']);
        });

        Schema::create('calendar_events', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('kind', 12);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('location', 200)->nullable();
            $table->foreignId('organizer_user_id')->constrained('users');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('timezone', 64);
            $table->string('visibility', 10)->default('normal');
            $table->jsonb('recurrence')->nullable();
            $table->jsonb('exdates')->nullable();
            $table->foreignId('series_id')->nullable()->constrained('calendar_events')->cascadeOnDelete();
            $table->timestampTz('original_start')->nullable();
            $table->foreignId('booking_page_id')->nullable()->constrained('booking_pages')->nullOnDelete();
            $table->unsignedInteger('sequence')->default(0);
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampsTz();

            $table->index(['starts_at', 'ends_at']);
            $table->index(['series_id', 'original_start']);
            $table->index(['booking_page_id', 'starts_at']);
        });

        Schema::create('event_participants', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('calendar_event_id')->constrained('calendar_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 10);
            $table->string('response', 10);
            $table->timestampTz('responded_at')->nullable();
            $table->timestampsTz();

            $table->unique(['calendar_event_id', 'user_id']);
            $table->index(['user_id', 'response']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_participants');
        Schema::dropIfExists('calendar_events');
        Schema::dropIfExists('booking_pages');
    }
};
