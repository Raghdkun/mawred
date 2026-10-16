<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hiring, Phase 6b (D89–D96): interviews with their panels and self-booking, scorecards against
 * per-requisition criteria, the candidate messages log, stage automations, offers with their
 * approval and e-signature, and the employee a hired application became.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pipeline_stages', function (Blueprint $table): void {
            $table->jsonb('automation')->nullable();
        });

        Schema::table('job_applications', function (Blueprint $table): void {
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->decimal('score', 3, 2)->nullable();
        });

        Schema::create('interviews', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('job_application_id')->constrained('job_applications')->cascadeOnDelete();
            $table->string('title', 160);
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('mode', 10)->default('onsite');
            $table->string('location', 200)->nullable();
            $table->string('timezone', 64);
            $table->string('status', 16);
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->foreignId('calendar_event_id')->nullable()->constrained('calendar_events')->nullOnDelete();
            $table->timestampTz('booking_sent_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index('job_application_id');
            $table->index(['status', 'starts_at']);
        });

        Schema::create('interview_panelists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('interview_id')->constrained('interviews')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestampsTz();

            $table->unique(['interview_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('scorecard_criteria', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            // Null is the default list that new requisitions copy.
            $table->foreignId('job_requisition_id')->nullable()->constrained('job_requisitions')->cascadeOnDelete();
            $table->jsonb('name');
            $table->unsignedSmallInteger('position');
            $table->timestampsTz();

            $table->index(['job_requisition_id', 'position']);
        });

        Schema::create('scorecards', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('interview_id')->constrained('interviews')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->jsonb('ratings');
            $table->string('recommendation', 12)->nullable();
            $table->text('comment')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampsTz();

            $table->unique(['interview_id', 'user_id']);
        });

        Schema::create('candidate_messages', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('job_application_id')->nullable()->constrained('job_applications')->nullOnDelete();
            $table->string('direction', 4);
            $table->string('channel', 10);
            $table->string('purpose', 16);
            $table->string('subject', 200)->nullable();
            // Message text is personal data (ADR-0007).
            $table->text('body');
            $table->string('status', 10);
            $table->string('error', 300)->nullable();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at');

            $table->index(['candidate_id', 'created_at']);
        });

        Schema::create('offers', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('job_application_id')->constrained('job_applications')->cascadeOnDelete();
            $table->string('status', 16);
            $table->string('job_title', 160);
            $table->date('start_on');
            // Pay is encrypted like employee salaries; the currency stays readable.
            $table->text('salary_minor');
            $table->char('salary_currency', 3);
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('employment_type_id')->nullable()->constrained('employment_types')->nullOnDelete();
            $table->foreignId('manager_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->char('locale', 2);
            $table->text('letter');
            $table->date('expires_on')->nullable();
            $table->foreignId('workflow_instance_id')->nullable()->constrained('workflow_instances')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('responded_at')->nullable();
            $table->string('signed_name', 160)->nullable();
            $table->string('signature_path', 200)->nullable();
            $table->char('signed_ip_hash', 64)->nullable();
            $table->string('signed_pdf_path', 200)->nullable();
            $table->text('decline_reason')->nullable();
            $table->timestampsTz();

            $table->index(['job_application_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
        Schema::dropIfExists('candidate_messages');
        Schema::dropIfExists('scorecards');
        Schema::dropIfExists('scorecard_criteria');
        Schema::dropIfExists('interview_panelists');
        Schema::dropIfExists('interviews');

        Schema::table('job_applications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('employee_id');
            $table->dropColumn('score');
        });

        Schema::table('pipeline_stages', function (Blueprint $table): void {
            $table->dropColumn('automation');
        });
    }
};
