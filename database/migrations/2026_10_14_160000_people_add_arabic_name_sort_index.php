<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The directory sorts by the name in the UI language. Arabic falls back to the English name; a
 * stored generated column keeps it a plain, indexable column that cursor pagination can use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->string('name_ar_sort', 160)->storedAs('coalesce(name_ar, name_en)');
            $table->index(['status', 'name_ar_sort']);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropIndex(['status', 'name_ar_sort']);
            $table->dropColumn('name_ar_sort');
        });
    }
};
