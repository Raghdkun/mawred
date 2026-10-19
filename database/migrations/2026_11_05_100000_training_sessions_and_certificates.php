<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Training, Phase 7b (D101, D103–D105, D107–D110): instructor-led sessions with bookings, the
 * waitlist and attendance; course feedback; certificates with a public serial, expiry and
 * renewal; and external training recorded from approved training requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_sessions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('timezone', 64);
            $table->string('mode', 8)->default('onsite');
            $table->string('location', 200)->nullable();
            $table->string('meeting_url', 500)->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->foreignId('trainer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('calendar_event_id')->nullable()->constrained('calendar_events')->nullOnDelete();
            $table->text('checkin_secret');
            $table->string('status', 12)->default('scheduled');
            $table->timestampsTz();

            $table->index(['course_id', 'starts_at']);
            $table->index(['status', 'starts_at']);
            $table->index('trainer_user_id');
        });

        Schema::create('session_bookings', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('training_session_id')->constrained('training_sessions')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->string('status', 12);
            $table->timestampTz('booked_at')->nullable();
            $table->timestampTz('waitlisted_at')->nullable();
            $table->timestampTz('attended_at')->nullable();
            $table->string('check_in', 8)->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['training_session_id', 'enrollment_id']);
            $table->index(['training_session_id', 'status', 'waitlisted_at']);
            $table->index('enrollment_id');
        });

        Schema::create('course_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_id')->unique()->constrained('enrollments')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestampsTz();

            $table->index(['course_id', 'created_at']);
        });

        Schema::create('external_trainings', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('request_id')->nullable()->unique()->constrained('requests')->nullOnDelete();
            $table->string('title', 200);
            $table->string('provider', 200)->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->bigInteger('cost_minor')->nullable();
            $table->char('cost_currency', 3)->nullable();
            $table->string('certificate_path', 300)->nullable();
            $table->string('certificate_name', 200)->nullable();
            $table->foreignId('enrollment_id')->nullable()->constrained('enrollments')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['employee_id', 'starts_on']);
        });

        Schema::create('certificates', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('serial', 24)->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->foreignId('enrollment_id')->nullable()->unique()->constrained('enrollments')->nullOnDelete();
            $table->jsonb('title');
            $table->jsonb('holder');
            $table->string('locale', 2)->default('en');
            $table->date('issued_on');
            $table->date('expires_on')->nullable();
            $table->jsonb('reminders_sent')->default('[]');
            $table->foreignId('renewal_enrollment_id')->nullable()->constrained('enrollments')->nullOnDelete();
            $table->timestampTz('revoked_at')->nullable();
            $table->text('revoked_reason')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('pdf_path', 300)->nullable();
            $table->timestampsTz();

            $table->index(['employee_id', 'issued_on']);
            $table->index('course_id');
        });

        DB::statement('CREATE INDEX certificates_expiring ON certificates (expires_on) WHERE revoked_at IS NULL AND expires_on IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('external_trainings');
        Schema::dropIfExists('course_feedback');
        Schema::dropIfExists('session_bookings');
        Schema::dropIfExists('training_sessions');
    }
};
