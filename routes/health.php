<?php

declare(strict_types=1);

use App\Http\Controllers\Public\HealthController;
use Illuminate\Support\Facades\Route;

/*
| Health probes (ADR-0019), outside the `web` group: no session, cookies or CSRF, and no rate
| limiter, which would itself need Redis. `/up` stays for Docker's health check.
*/
Route::prefix('health')->name('health.')->group(function (): void {
    Route::get('live', [HealthController::class, 'live'])->name('live');
    Route::get('ready', [HealthController::class, 'ready'])->name('ready');
});
