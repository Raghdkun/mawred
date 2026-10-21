<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Browsers and phones a person turned push notifications on for (D128). The endpoint is unique
 * per device; the keys are encrypted at rest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique();
            $table->text('public_key');
            $table->text('auth_token');
            $table->string('content_encoding', 16)->default('aes128gcm');
            $table->string('user_agent', 200)->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampsTz();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
