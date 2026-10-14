<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Workflow engine (ADR-0009, architecture §6): versioned graphs, instances pinned to a version,
 * one step row per node activation, human tasks, an append-only timeline, single-use action
 * tokens for link approvals, delegations, and outbound webhook endpoints.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_definitions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('code', 40)->unique();
            $table->jsonb('name');
            $table->jsonb('description')->nullable();
            $table->string('category', 40)->nullable();
            $table->unsignedBigInteger('published_version_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('workflow_versions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('workflow_definition_id')->constrained('workflow_definitions')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('status', 12);
            $table->jsonb('graph');
            $table->jsonb('layout')->nullable();
            $table->char('checksum', 64)->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['workflow_definition_id', 'number']);
        });

        DB::statement("CREATE UNIQUE INDEX workflow_versions_one_draft ON workflow_versions (workflow_definition_id) WHERE status = 'draft'");

        Schema::table('workflow_definitions', function (Blueprint $table): void {
            $table->foreign('published_version_id')->references('id')->on('workflow_versions')->nullOnDelete();
        });

        Schema::create('workflow_instances', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('workflow_version_id')->constrained('workflow_versions');
            $table->foreignId('workflow_definition_id')->constrained('workflow_definitions');
            $table->nullableMorphs('subject');
            $table->string('status', 16);
            $table->string('outcome', 16)->nullable();
            $table->jsonb('context');
            $table->foreignId('requester_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('requester_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('parent_step_id')->nullable();
            $table->string('correlation_key', 120)->nullable()->unique();
            $table->text('attention_reason')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'updated_at']);
        });

        Schema::create('workflow_steps', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('workflow_instance_id')->constrained('workflow_instances')->cascadeOnDelete();
            $table->string('node_id', 64);
            $table->string('node_type', 24);
            $table->unsignedInteger('iteration')->default(1);
            $table->string('branch_key', 64)->default('');
            $table->string('status', 12);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('available_at')->nullable();
            $table->timestampTz('due_at')->nullable();
            $table->jsonb('input')->nullable();
            $table->jsonb('output')->nullable();
            $table->text('error')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();

            $table->unique(['workflow_instance_id', 'node_id', 'iteration', 'branch_key']);
            $table->index(['status', 'available_at']);
        });

        Schema::table('workflow_instances', function (Blueprint $table): void {
            $table->foreign('parent_step_id')->references('id')->on('workflow_steps')->nullOnDelete();
        });

        Schema::create('workflow_tasks', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('workflow_instance_id')->constrained('workflow_instances')->cascadeOnDelete();
            $table->foreignId('workflow_step_id')->constrained('workflow_steps')->cascadeOnDelete();
            $table->string('kind', 12);
            $table->foreignId('assignee_user_id')->constrained('users');
            $table->foreignId('original_assignee_user_id')->constrained('users');
            $table->foreignId('delegated_from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 12);
            $table->string('decision', 12)->nullable();
            $table->text('comment')->nullable();
            $table->string('decided_via', 16)->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('remind_at')->nullable();
            $table->timestampTz('reminded_at')->nullable();
            $table->timestampTz('escalated_at')->nullable();
            $table->timestampsTz();

            $table->index(['assignee_user_id', 'status', 'created_at']);
            $table->index(['status', 'due_at']);
            $table->index(['status', 'remind_at']);
        });

        Schema::create('workflow_timeline', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_instance_id')->constrained('workflow_instances')->cascadeOnDelete();
            $table->foreignId('workflow_step_id')->nullable()->constrained('workflow_steps')->nullOnDelete();
            $table->string('event', 32);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('data')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['workflow_instance_id', 'id']);
        });

        Schema::create('action_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_task_id')->constrained('workflow_tasks')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->string('decision', 12);
            $table->string('channel', 16);
            $table->timestampTz('expires_at');
            $table->timestampTz('used_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['workflow_task_id', 'used_at']);
        });

        Schema::create('delegations', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->jsonb('definition_ids')->nullable();
            $table->string('reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['from_user_id', 'starts_on', 'ends_on']);
        });

        Schema::create('webhook_endpoints', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('name', 120);
            $table->string('url', 500);
            // HMAC signing secret (ADR-0009). Moves to the credential vault in Phase 4.
            $table->text('secret');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoints');
        Schema::dropIfExists('delegations');
        Schema::dropIfExists('action_tokens');
        Schema::dropIfExists('workflow_timeline');
        Schema::dropIfExists('workflow_tasks');
        Schema::table('workflow_instances', fn (Blueprint $table) => $table->dropForeign(['parent_step_id']));
        Schema::dropIfExists('workflow_steps');
        Schema::dropIfExists('workflow_instances');
        Schema::table('workflow_definitions', fn (Blueprint $table) => $table->dropForeign(['published_version_id']));
        Schema::dropIfExists('workflow_versions');
        Schema::dropIfExists('workflow_definitions');
    }
};
