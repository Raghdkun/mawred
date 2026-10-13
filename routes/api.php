<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
| /api/v1 (ADR-0012). Sanctum tokens for the Flutter app and integration bots; Sanctum stateful
| cookies for the web app's async widgets. Every mutating endpoint accepts `Idempotency-Key`.
*/
Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(['auth:sanctum', 'idempotent'])
    ->group(function (): void {
        // Domain route files are registered here as phases land.
    });
