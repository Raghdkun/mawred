<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bulk imports (screen 9): the uploaded file, its column mapping and progress, and one row per
 * spreadsheet line with its raw values, normalised values, errors and outcome.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('type', 32);
            $table->string('status', 16);
            $table->string('original_name', 255);
            $table->jsonb('headers')->default('[]');
            $table->jsonb('mapping')->default('{}');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('to_create')->default(0);
            $table->unsignedInteger('to_update')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('created_rows')->default(0);
            $table->unsignedInteger('updated_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->string('failure', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('validated_at')->nullable();
            $table->timestampTz('committed_at')->nullable();
            $table->timestampsTz();

            $table->index(['type', 'created_at']);
        });

        Schema::create('import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_id')->constrained('imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->jsonb('raw');
            $table->jsonb('normalized')->nullable();
            $table->jsonb('errors')->nullable();
            $table->string('status', 16);
            $table->string('action', 8)->nullable();
            $table->unsignedSmallInteger('level')->default(0);
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();

            $table->unique(['import_id', 'row_number']);
            $table->index(['import_id', 'status', 'level', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('imports');
    }
};
