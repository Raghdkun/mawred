<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Integrations\IntegrationsController;
use Illuminate\Support\Facades\Route;

/*
| Integrations hub and settings (ADR-0015, handoff 40–41).
*/
Route::middleware(['auth', 'verified'])->prefix('integrations')->name('integrations.')->group(function (): void {
    Route::get('/', [IntegrationsController::class, 'index'])->name('index');
    Route::get('email', [IntegrationsController::class, 'email'])->name('email');
    Route::post('email', [IntegrationsController::class, 'saveEmail'])->name('email.update');
    Route::post('email/test', [IntegrationsController::class, 'testEmail'])->middleware('throttle:6,1')->name('email.test');
    Route::post('{type}/check', [IntegrationsController::class, 'test'])->whereIn('type', ['email', 'whatsapp_official', 'whatsapp_waha', 'cognito'])->middleware('throttle:12,1')->name('check');
});
