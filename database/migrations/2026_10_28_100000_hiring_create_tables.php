<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hiring, Phase 6a (D80–D87): requisitions approved through the workflow engine, their hiring
 * team and pipeline, bilingual postings on the careers site, candidates (deduplicated on email,
 * flagged on phone) and their applications with a stage history.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SEQUENCE IF NOT EXISTS job_requisition_number_seq');

        Schema::create('job_requisitions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('number', 20)->unique();
            $table->jsonb('title');
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('employment_type_id')->nullable()->constrained('employment_types')->nullOnDelete();
            $table->unsignedSmallInteger('headcount')->default(1);
            $table->string('reason', 16)->default('new');
            $table->foreignId('hiring_manager_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('request_id')->nullable()->unique()->constrained('requests')->nullOnDelete();
            $table->bigInteger('salary_min_minor')->nullable();
            $table->bigInteger('salary_max_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->date('target_start_on')->nullable();
            $table->text('justification')->nullable();
            $table->string('status', 16)->default('pending');
            $table->timestampTz('opened_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'branch_id']);
            $table->index(['department_id', 'status']);
            $table->index('hiring_manager_employee_id');
            $table->index(['status', 'created_at']);
        });

        Schema::create('requisition_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_requisition_id')->constrained('job_requisitions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 16);
            $table->timestampsTz();

            $table->unique(['job_requisition_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('pipeline_stages', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            // Null is the default template that new requisitions copy.
            $table->foreignId('job_requisition_id')->nullable()->constrained('job_requisitions')->cascadeOnDelete();
            $table->jsonb('name');
            $table->string('kind', 12);
            $table->unsignedSmallInteger('position');
            $table->timestampsTz();

            $table->index(['job_requisition_id', 'position']);
        });

        Schema::create('job_postings', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('job_requisition_id')->constrained('job_requisitions')->cascadeOnDelete();
            $table->string('slug', 120)->unique();
            $table->jsonb('title');
            $table->jsonb('summary')->nullable();
            $table->jsonb('description');
            $table->jsonb('location')->nullable();
            $table->string('workplace', 10)->default('onsite');
            $table->boolean('show_salary')->default(false);
            $table->foreignId('form_id')->nullable()->constrained('forms')->nullOnDelete();
            $table->foreignId('cognito_source_id')->nullable()->constrained('cognito_sources')->nullOnDelete();
            $table->jsonb('knockouts')->nullable();
            $table->jsonb('seo')->nullable();
            $table->string('status', 10)->default('draft');
            $table->timestampTz('published_at')->nullable();
            $table->date('closes_on')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'published_at']);
            $table->index('job_requisition_id');
        });

        Schema::create('candidates', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('name', 160);
            // Contact details are personal data (ADR-0007): ciphertext + blind index for matching.
            $table->text('email')->nullable();
            $table->char('email_bidx', 64)->nullable()->index();
            $table->text('phone')->nullable();
            $table->char('phone_bidx', 64)->nullable()->index();
            $table->char('locale', 2)->default('en');
            $table->string('location', 120)->nullable();
            $table->string('source', 16);
            $table->boolean('talent_pool')->default(false);
            $table->timestampTz('talent_pool_consented_at')->nullable();
            $table->timestampTz('consented_at')->nullable();
            $table->foreignId('possible_duplicate_of_id')->nullable()->constrained('candidates')->nullOnDelete();
            $table->foreignId('merged_into_id')->nullable()->constrained('candidates')->nullOnDelete();
            $table->timestampTz('anonymised_at')->nullable();
            $table->timestampTz('last_activity_at');
            $table->text('search_text')->default('');
            $table->timestampsTz();

            $table->index(['talent_pool', 'last_activity_at']);
            $table->index('possible_duplicate_of_id');
            $table->index(['anonymised_at', 'last_activity_at']);
        });

        DB::statement('CREATE INDEX candidates_search_text_trgm ON candidates USING gin (search_text gin_trgm_ops)');

        Schema::create('hiring_tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 40);
            $table->timestampsTz();
        });

        DB::statement('CREATE UNIQUE INDEX hiring_tags_name_unique ON hiring_tags (lower(name))');

        Schema::create('candidate_tag', function (Blueprint $table): void {
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('hiring_tag_id')->constrained('hiring_tags')->cascadeOnDelete();

            $table->primary(['candidate_id', 'hiring_tag_id']);
            $table->index('hiring_tag_id');
        });

        Schema::create('job_applications', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('job_requisition_id')->constrained('job_requisitions')->cascadeOnDelete();
            $table->foreignId('job_posting_id')->nullable()->constrained('job_postings')->nullOnDelete();
            $table->foreignId('pipeline_stage_id')->constrained('pipeline_stages');
            $table->string('status', 12)->default('active');
            $table->string('source', 16);
            $table->foreignId('form_submission_id')->nullable()->constrained('form_submissions')->nullOnDelete();
            $table->foreignId('cv_upload_id')->nullable()->constrained('form_uploads')->nullOnDelete();
            $table->boolean('knocked_out')->default(false);
            $table->jsonb('knockout_reasons')->nullable();
            $table->string('rejection_reason', 24)->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->timestampTz('applied_at');
            $table->timestampTz('stage_entered_at');
            $table->timestampTz('last_activity_at');
            $table->timestampsTz();

            $table->unique(['candidate_id', 'job_requisition_id']);
            $table->index(['job_requisition_id', 'status', 'pipeline_stage_id']);
            $table->index(['pipeline_stage_id', 'stage_entered_at']);
            $table->index(['status', 'applied_at']);
        });

        Schema::create('application_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_application_id')->constrained('job_applications')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->foreignId('from_stage_id')->nullable()->constrained('pipeline_stages')->nullOnDelete();
            $table->foreignId('to_stage_id')->nullable()->constrained('pipeline_stages')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body')->nullable();
            $table->jsonb('data')->nullable();
            $table->timestampTz('created_at');

            $table->index(['job_application_id', 'created_at']);
            $table->index(['kind', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_events');
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('candidate_tag');
        Schema::dropIfExists('hiring_tags');
        Schema::dropIfExists('candidates');
        Schema::dropIfExists('job_postings');
        Schema::dropIfExists('pipeline_stages');
        Schema::dropIfExists('requisition_members');
        Schema::dropIfExists('job_requisitions');
        DB::statement('DROP SEQUENCE IF EXISTS job_requisition_number_seq');
    }
};
