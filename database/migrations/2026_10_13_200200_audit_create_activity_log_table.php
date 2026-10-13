<?php

declare(strict_types=1);

use App\Support\Database\PartitionManager;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * spatie/laravel-activitylog v5 table, partitioned monthly (ADR-0006 / ADR-0018), with the
 * extra context columns every audit entry carries.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE activity_log (
                id bigint GENERATED ALWAYS AS IDENTITY,
                log_name varchar(255) NULL,
                description text NOT NULL,
                subject_type varchar(255) NULL,
                subject_id bigint NULL,
                event varchar(255) NULL,
                causer_type varchar(255) NULL,
                causer_id bigint NULL,
                attribute_changes jsonb NULL,
                properties jsonb NULL,
                actor_type varchar(16) NULL,
                channel varchar(16) NULL,
                impersonator_id bigint NULL,
                ip inet NULL,
                user_agent_hash char(64) NULL,
                request_id varchar(64) NULL,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NULL,
                PRIMARY KEY (id, created_at)
            ) PARTITION BY RANGE (created_at)
            SQL);

        DB::statement('CREATE INDEX activity_log_subject_idx ON activity_log (subject_type, subject_id, created_at DESC)');
        DB::statement('CREATE INDEX activity_log_causer_idx ON activity_log (causer_type, causer_id, created_at DESC)');
        DB::statement('CREATE INDEX activity_log_log_name_idx ON activity_log (log_name, created_at DESC)');
        DB::statement('CREATE INDEX activity_log_request_idx ON activity_log (request_id)');

        resolve(PartitionManager::class)->ensureMonthlyPartitions('activity_log', CarbonImmutable::now()->subMonth(), 4);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS activity_log CASCADE');
    }
};
