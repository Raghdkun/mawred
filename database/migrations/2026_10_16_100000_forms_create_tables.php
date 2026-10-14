<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Forms (ADR-0008): definitions, frozen versions (one draft at a time), submissions pinned to a
 * version, and temporary uploads attached on submit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('code', 40)->unique();
            $table->jsonb('name');
            $table->jsonb('description')->nullable();
            $table->string('category', 40)->nullable();
            $table->unsignedBigInteger('published_version_id')->nullable();
            $table->boolean('is_public')->default(false);
            $table->string('public_slug', 80)->nullable()->unique();
            $table->jsonb('public_settings')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['category', 'deleted_at']);
        });

        Schema::create('form_versions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('status', 12);
            $table->jsonb('schema');
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['form_id', 'number']);
        });

        // One draft per form at a time.
        DB::statement("CREATE UNIQUE INDEX form_versions_one_draft ON form_versions (form_id) WHERE status = 'draft'");

        Schema::table('forms', function (Blueprint $table): void {
            $table->foreign('published_version_id')->references('id')->on('form_versions')->nullOnDelete();
        });

        Schema::create('form_submissions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->foreignId('form_version_id')->constrained('form_versions');
            // Answers can hold personal data: encrypted at rest.
            $table->text('data');
            $table->string('source', 12);
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->char('ip_hash', 64)->nullable();
            $table->string('locale', 5)->default('en');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['form_id', 'created_at']);
            $table->index(['form_id', 'source', 'created_at']);
        });

        Schema::create('form_uploads', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->string('field_key', 40);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 120);
            $table->unsignedBigInteger('size');
            // Who may attach it: a signed-in user, or a public visitor's session.
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->char('session_hash', 64)->nullable();
            $table->foreignId('form_submission_id')->nullable()->constrained('form_submissions')->cascadeOnDelete();
            $table->timestampTz('expires_at');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['expires_at', 'form_submission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_uploads');
        Schema::dropIfExists('form_submissions');
        Schema::table('forms', fn (Blueprint $table) => $table->dropForeign(['published_version_id']));
        Schema::dropIfExists('form_versions');
        Schema::dropIfExists('forms');
    }
};
