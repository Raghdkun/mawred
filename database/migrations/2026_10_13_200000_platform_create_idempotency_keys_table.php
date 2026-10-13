<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replay store for `Idempotency-Key` on mutating API requests (ADR-0012).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('owner', 100);
            $table->string('key', 255);
            $table->char('request_hash', 64);
            $table->string('status', 16); // processing | completed
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->jsonb('response_headers')->nullable();
            $table->text('response_body')->nullable();
            $table->timestampTz('expires_at')->index();
            $table->timestampsTz();

            $table->unique(['owner', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
