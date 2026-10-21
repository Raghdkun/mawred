<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Addresses Mawred stops emailing (D127): hard bounces and spam complaints reported by the email
 * provider, until an admin allows the address again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_suppressions', function (Blueprint $table): void {
            $table->id();
            $table->char('ulid', 26)->unique();
            $table->string('email', 254)->unique();
            $table->string('reason', 20);
            $table->string('detail', 300)->nullable();
            $table->string('source', 20);
            $table->timestampsTz();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_suppressions');
    }
};
