<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Organization structure (brief §5): branches, an unlimited-depth department tree (ltree, ADR-0005),
 * positions, employment types, grades and cost centres. Unlimited rows everywhere (brief §3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_centers', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('code', 32)->unique();
            $table->jsonb('name');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('grades', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('code', 32)->unique();
            $table->jsonb('name');
            $table->unsignedSmallInteger('rank')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('employment_types', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('code', 32)->unique();
            $table->jsonb('name');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('code', 32)->unique();
            $table->jsonb('name');
            $table->jsonb('address')->nullable(); // {line1, line2, city, postal_code}
            $table->char('country_code', 2);
            $table->string('timezone', 64);
            $table->char('default_currency', 3);
            $table->char('phone_region', 2);
            $table->jsonb('weekend_days'); // ISO weekdays, 1 = Monday … 7 = Sunday
            $table->time('work_starts_at')->default('09:00');
            $table->time('work_ends_at')->default('17:00');
            $table->decimal('geofence_lat', 9, 6)->nullable();
            $table->decimal('geofence_lng', 9, 6)->nullable();
            $table->unsignedInteger('geofence_radius_m')->nullable();
            $table->unsignedBigInteger('manager_employee_id')->nullable(); // FK added with employees
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('search_text')->default('');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['is_active', 'id']);
        });

        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('code', 32)->unique();
            $table->jsonb('name');
            $table->foreignId('parent_id')->nullable()->constrained('departments')->restrictOnDelete();
            $table->unsignedSmallInteger('depth')->default(0);
            $table->unsignedBigInteger('head_employee_id')->nullable(); // FK added with employees
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('search_text')->default('');
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['parent_id', 'id']);
        });

        // Materialised path of ids ("1.5.12"): subtree checks are one indexed predicate (ADR-0005).
        DB::statement("ALTER TABLE departments ADD COLUMN path ltree NOT NULL DEFAULT ''");
        DB::statement('CREATE INDEX departments_path_gist ON departments USING GIST (path)');

        Schema::create('positions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('code', 32)->unique();
            $table->jsonb('title');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('grade_id')->nullable()->constrained('grades')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('search_text')->default('');
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        foreach (['branches', 'departments', 'positions'] as $table) {
            DB::statement("CREATE INDEX {$table}_search_trgm ON {$table} USING GIN (search_text gin_trgm_ops)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('employment_types');
        Schema::dropIfExists('grades');
        Schema::dropIfExists('cost_centers');
    }
};
