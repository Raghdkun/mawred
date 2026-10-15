<?php

declare(strict_types=1);

use App\Support\Database\PartitionManager;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp (ADR-0015, D58, D59): verified numbers per person, the approved templates synced from
 * Meta, a monthly-partitioned message log, and the WhatsApp template mapping on notification
 * templates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('phone_e164', 20);
            $table->timestampTz('verified_at')->nullable();
            $table->string('code_hash', 64)->nullable();
            $table->timestampTz('code_expires_at')->nullable();
            $table->unsignedSmallInteger('code_attempts')->default(0);
            $table->timestampTz('last_inbound_at')->nullable();
            $table->timestampsTz();

            $table->index('phone_e164');
        });

        Schema::create('whatsapp_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('language', 12);
            $table->string('category', 24)->nullable();
            $table->string('status', 24);
            $table->jsonb('components')->default('[]');
            $table->timestampTz('synced_at');
            $table->timestampsTz();

            $table->unique(['name', 'language']);
        });

        DB::statement(<<<'SQL'
            CREATE TABLE whatsapp_messages (
                id bigserial,
                ulid char(26) NOT NULL,
                user_id bigint NULL,
                driver varchar(24) NOT NULL,
                purpose varchar(48) NOT NULL,
                phone_last4 varchar(4) NULL,
                phone_hash char(64) NULL,
                template varchar(120) NULL,
                status varchar(12) NOT NULL,
                provider_id varchar(160) NULL,
                error_code varchar(32) NULL,
                error text NULL,
                delivered_at timestamptz NULL,
                read_at timestamptz NULL,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NULL,
                PRIMARY KEY (id, created_at)
            ) PARTITION BY RANGE (created_at)
            SQL);
        DB::statement('CREATE INDEX whatsapp_messages_provider_idx ON whatsapp_messages (provider_id)');
        DB::statement('CREATE INDEX whatsapp_messages_user_idx ON whatsapp_messages (user_id, created_at DESC)');
        DB::statement('CREATE INDEX whatsapp_messages_status_idx ON whatsapp_messages (driver, status, created_at DESC)');
        resolve(PartitionManager::class)->ensureMonthlyPartitions('whatsapp_messages', CarbonImmutable::now()->subMonth(), 4);

        Schema::table('notification_templates', function (Blueprint $table): void {
            $table->jsonb('wa_template')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('notification_templates', fn (Blueprint $table) => $table->dropColumn('wa_template'));
        DB::statement('DROP TABLE IF EXISTS whatsapp_messages CASCADE');
        Schema::dropIfExists('whatsapp_templates');
        Schema::dropIfExists('whatsapp_contacts');
    }
};
