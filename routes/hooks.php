<?php

declare(strict_types=1);

use App\Http\Controllers\Public\CognitoHookController;
use App\Http\Controllers\Public\WhatsAppHookController;
use Illuminate\Support\Facades\Route;

/*
| Inbound webhooks (ADR-0015). No session, CSRF or auth: each endpoint authenticates the caller
| itself (a secret token in the URL for Cognito, signatures for WhatsApp) and is rate limited.
*/
Route::prefix('hooks')->name('hooks.')->group(function (): void {
    Route::post('cognito/{token}/{event}', CognitoHookController::class)
        ->whereIn('event', ['submit', 'update', 'delete'])
        ->middleware('throttle:cognito')
        ->name('cognito');

    Route::get('whatsapp/official', [WhatsAppHookController::class, 'verify'])->middleware('throttle:60,1')->name('whatsapp.verify');
    Route::post('whatsapp/official', [WhatsAppHookController::class, 'official'])->middleware('throttle:600,1')->name('whatsapp.official');
    Route::post('whatsapp/waha', [WhatsAppHookController::class, 'waha'])->middleware('throttle:600,1')->name('whatsapp.waha');
});
