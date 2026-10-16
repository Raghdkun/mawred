<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cognito-backed postings (D81, D97): a Cognito source feeds at most one posting, and each
 * receipt remembers the application it created, for the submission log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table): void {
            $table->unique('cognito_source_id');
        });

        Schema::table('cognito_receipts', function (Blueprint $table): void {
            $table->foreignId('job_application_id')->nullable()->after('request_id')->constrained('job_applications')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cognito_receipts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('job_application_id');
        });

        Schema::table('job_postings', function (Blueprint $table): void {
            $table->dropUnique(['cognito_source_id']);
        });
    }
};
