<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RBAC + data scopes + field permissions (ADR-0005), on top of spatie/laravel-permission.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->char('ulid', 26)->nullable()->unique()->after('id');
            $table->jsonb('label')->nullable();
            $table->jsonb('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_super_admin')->default(false);
            $table->boolean('api_only')->default(false);
            $table->boolean('requires_2fa')->default(false);
            $table->string('home_layout', 16)->default('employee');
            $table->unsignedSmallInteger('rank')->default(0);
        });

        Schema::table('permissions', function (Blueprint $table): void {
            $table->string('module', 48)->nullable()->index();
            $table->string('action', 16)->nullable();
        });

        Schema::create('role_permission_scopes', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->string('scope', 20);
            $table->timestampsTz();

            $table->primary(['role_id', 'permission_id']);
        });

        // Anchors narrow a user's branch/department-scoped grants to specific units (D5).
        // FKs to branches/departments are added in Phase 1 when those tables exist.
        Schema::create('role_assignment_anchors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('anchor_type', 16);
            $table->unsignedBigInteger('anchor_id');
            $table->timestampsTz();

            $table->unique(['user_id', 'role_id', 'anchor_type', 'anchor_id']);
            $table->index(['anchor_type', 'anchor_id']);
        });

        Schema::create('field_permissions', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('field_key', 96);
            $table->string('access', 8);
            $table->timestampsTz();

            $table->primary(['role_id', 'field_key']);
        });

        Schema::create('user_sessions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->char('session_hash', 64)->unique();
            $table->ipAddress('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('last_seen_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'revoked_at', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
        Schema::dropIfExists('field_permissions');
        Schema::dropIfExists('role_assignment_anchors');
        Schema::dropIfExists('role_permission_scopes');

        Schema::table('permissions', function (Blueprint $table): void {
            $table->dropColumn(['module', 'action']);
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn(['ulid', 'label', 'description', 'is_system', 'is_super_admin', 'api_only', 'requires_2fa', 'home_layout', 'rank']);
        });
    }
};
