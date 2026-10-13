<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->string('id', 36)->primary(); // ULIDs from Phase 3's base notification (ADR-0003)
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestampTz('read_at')->nullable();
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
