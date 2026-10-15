<?php

declare(strict_types=1);

use App\Support\Database\PartitionManager;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cognito Forms (ADR-0015, D19, D60, D61): sources with a secret URL token (only its hash is
 * stored), every received payload kept raw and encrypted in a monthly-partitioned receipts
 * table, and the link from a request back to the entry it came from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cognito_sources', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('name', 120);
            $table->char('token_hash', 64)->unique();
            $table->string('token_last4', 4);
            $table->foreignId('request_type_id')->nullable()->constrained('request_types')->nullOnDelete();
            $table->string('form_url', 500)->nullable();
            $table->jsonb('mapping')->default('{}');
            $table->boolean('capture_sample')->default(false);
            $table->text('sample_payload')->nullable();
            $table->timestampTz('sample_captured_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('rotated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement(<<<'SQL'
            CREATE TABLE cognito_receipts (
                id bigserial,
                ulid char(26) NOT NULL,
                cognito_source_id bigint NOT NULL,
                event varchar(8) NOT NULL,
                entry_id varchar(64) NULL,
                entry_version varchar(32) NULL,
                idempotency_key varchar(160) NOT NULL,
                payload text NOT NULL,
                status varchar(16) NOT NULL,
                error text NULL,
                request_id bigint NULL,
                employee_id bigint NULL,
                attempts smallint NOT NULL DEFAULT 0,
                processed_at timestamptz NULL,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NULL,
                PRIMARY KEY (id, created_at)
            ) PARTITION BY RANGE (created_at)
            SQL);
        DB::statement('CREATE INDEX cognito_receipts_source_idx ON cognito_receipts (cognito_source_id, created_at DESC)');
        DB::statement('CREATE INDEX cognito_receipts_status_idx ON cognito_receipts (status, created_at DESC)');
        DB::statement('CREATE INDEX cognito_receipts_key_idx ON cognito_receipts (idempotency_key)');
        DB::statement('CREATE INDEX cognito_receipts_ulid_idx ON cognito_receipts (ulid)');
        resolve(PartitionManager::class)->ensureMonthlyPartitions('cognito_receipts', CarbonImmutable::now()->subMonth(), 4);

        Schema::table('request_types', function (Blueprint $table): void {
            $table->string('source', 16)->default('native');
        });

        Schema::table('requests', function (Blueprint $table): void {
            $table->foreignId('cognito_source_id')->nullable()->constrained('cognito_sources')->nullOnDelete();
            $table->string('external_entry_id', 64)->nullable();
            $table->index(['cognito_source_id', 'external_entry_id']);
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cognito_source_id');
            $table->dropColumn('external_entry_id');
        });
        Schema::table('request_types', fn (Blueprint $table) => $table->dropColumn('source'));
        DB::statement('DROP TABLE IF EXISTS cognito_receipts CASCADE');
        Schema::dropIfExists('cognito_sources');
    }
};
