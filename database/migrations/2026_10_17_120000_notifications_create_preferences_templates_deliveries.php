<?php

declare(strict_types=1);

use App\Support\Database\PartitionManager;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications (ADR-0016): per-user channel preferences by category, admin-editable templates
 * per event/channel/locale (rendered only through SafeTemplate), quiet hours and digest on the
 * user, and an append-only delivery log partitioned by month (ADR-0018).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('category', 24);
            $table->string('channel', 16);
            $table->boolean('enabled');
            $table->timestampsTz();

            $table->primary(['user_id', 'category', 'channel']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->boolean('digest_enabled')->default(false);
        });

        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('event', 48);
            $table->string('channel', 16);
            $table->string('locale', 5);
            $table->string('subject', 255)->nullable();
            $table->text('body');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['event', 'channel', 'locale']);
        });

        DB::statement(<<<'SQL'
            CREATE TABLE notification_deliveries (
                id bigserial,
                user_id bigint NULL,
                event varchar(48) NOT NULL,
                category varchar(24) NOT NULL,
                channel varchar(16) NOT NULL,
                status varchar(12) NOT NULL,
                idempotency_key varchar(120) NULL,
                error text NULL,
                created_at timestamptz NOT NULL DEFAULT now(),
                PRIMARY KEY (id, created_at)
            ) PARTITION BY RANGE (created_at)
            SQL);

        DB::statement('CREATE INDEX notification_deliveries_user_idx ON notification_deliveries (user_id, created_at DESC)');
        DB::statement('CREATE INDEX notification_deliveries_key_idx ON notification_deliveries (idempotency_key)');

        resolve(PartitionManager::class)->ensureMonthlyPartitions('notification_deliveries', CarbonImmutable::now()->subMonth(), 4);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS notification_deliveries CASCADE');
        Schema::dropIfExists('notification_templates');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['quiet_hours_start', 'quiet_hours_end', 'digest_enabled']));
        Schema::dropIfExists('notification_preferences');
    }
};
