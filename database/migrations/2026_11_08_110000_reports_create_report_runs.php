<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Report exports (D113, D114): each run of a report as a file, made on the `reports` queue as the
 * person who asked, kept for a week and downloadable only by them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_runs', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('report', 60);
            $table->jsonb('params');
            $table->string('format', 4);
            $table->string('locale', 2)->default('en');
            $table->string('status', 10)->default('queued');
            $table->unsignedInteger('rows')->nullable();
            $table->string('file_path', 300)->nullable();
            $table->string('file_name', 200)->nullable();
            $table->text('error')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_runs');
    }
};
