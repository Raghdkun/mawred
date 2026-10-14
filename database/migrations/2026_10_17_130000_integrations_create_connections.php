<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The credential vault (ADR-0015). Phase 3 uses it for the email connection (setup step 7);
 * Phase 4 adds WhatsApp, Cognito, health checks and key rotation on the same table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_connections', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('type', 32);
            $table->string('name', 64);
            $table->jsonb('config')->default('{}');
            $table->text('credentials')->nullable();
            $table->string('status', 16)->default('not_configured');
            $table->timestampTz('last_success_at')->nullable();
            $table->timestampTz('last_failure_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampTz('health_checked_at')->nullable();
            $table->timestampsTz();

            $table->unique(['type', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_connections');
    }
};
