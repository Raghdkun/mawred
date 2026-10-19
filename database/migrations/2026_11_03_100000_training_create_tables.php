<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Training, Phase 7a (D98–D100, D102): the catalog of courses with ordered modules (text, video,
 * file, quiz), quiz questions, learning paths, and enrolments with their module progress and
 * quiz attempts. Mandatory courses carry an audience; enrolments created for it record the source.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SEQUENCE IF NOT EXISTS course_code_seq');

        Schema::create('courses', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('code', 20)->unique();
            $table->jsonb('title');
            $table->jsonb('summary')->nullable();
            $table->jsonb('description')->nullable();
            $table->string('category', 20)->default('skills');
            $table->string('status', 12)->default('draft');
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->boolean('issues_certificate')->default(true);
            $table->unsignedSmallInteger('validity_months')->nullable();
            $table->jsonb('audience')->nullable();
            $table->unsignedSmallInteger('due_days')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'category']);
            $table->index('owner_user_id');
        });

        Schema::create('course_modules', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('kind', 8);
            $table->jsonb('title');
            $table->jsonb('body')->nullable();
            $table->string('url', 500)->nullable();
            $table->string('file_path', 300)->nullable();
            $table->string('file_name', 200)->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->boolean('required')->default(true);
            $table->unsignedTinyInteger('pass_mark')->nullable();
            $table->unsignedTinyInteger('max_attempts')->nullable();
            $table->timestampsTz();

            $table->index(['course_id', 'position']);
        });

        Schema::create('quiz_questions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('course_module_id')->constrained('course_modules')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('type', 12);
            $table->jsonb('prompt');
            $table->jsonb('options');
            $table->unsignedTinyInteger('points')->default(1);
            $table->timestampsTz();

            $table->index(['course_module_id', 'position']);
        });

        Schema::create('learning_paths', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->jsonb('title');
            $table->jsonb('description')->nullable();
            $table->string('status', 12)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index('status');
        });

        Schema::create('learning_path_courses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('learning_path_id')->constrained('learning_paths')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');

            $table->unique(['learning_path_id', 'course_id']);
            $table->index('course_id');
        });

        Schema::create('enrollments', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('learning_path_id')->nullable()->constrained('learning_paths')->nullOnDelete();
            $table->string('status', 12);
            $table->string('source', 12);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_on')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->foreignId('workflow_instance_id')->nullable()->constrained('workflow_instances')->nullOnDelete();
            $table->text('decision_note')->nullable();
            $table->timestampTz('requested_at')->nullable();
            $table->timestampTz('enrolled_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampsTz();

            $table->index(['employee_id', 'status']);
            $table->index(['course_id', 'status']);
            $table->index(['status', 'due_on']);
            $table->index('learning_path_id');
        });

        // One live enrolment per person and course; finished ones stay as history.
        DB::statement("CREATE UNIQUE INDEX enrollments_one_live ON enrollments (employee_id, course_id) WHERE status IN ('requested', 'enrolled')");

        Schema::create('module_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->foreignId('course_module_id')->constrained('course_modules')->cascadeOnDelete();
            $table->timestampTz('completed_at');

            $table->unique(['enrollment_id', 'course_module_id']);
        });

        Schema::create('quiz_attempts', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->foreignId('course_module_id')->constrained('course_modules')->cascadeOnDelete();
            $table->jsonb('answers');
            $table->unsignedTinyInteger('score');
            $table->boolean('passed');
            $table->timestampTz('submitted_at');

            $table->index(['enrollment_id', 'course_module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('module_progress');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('learning_path_courses');
        Schema::dropIfExists('learning_paths');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('course_modules');
        Schema::dropIfExists('courses');
        DB::statement('DROP SEQUENCE IF EXISTS course_code_seq');
    }
};
