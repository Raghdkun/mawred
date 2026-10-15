<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Integrations\CognitoController;
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
    Route::get('cognito', [CognitoController::class, 'index'])->name('cognito.index');
    Route::post('cognito/sources', [CognitoController::class, 'store'])->name('cognito.store');
    Route::get('cognito/sources/{source}', [CognitoController::class, 'show'])->name('cognito.show');
    Route::put('cognito/sources/{source}', [CognitoController::class, 'update'])->name('cognito.update');
    Route::post('cognito/sources/{source}/rotate', [CognitoController::class, 'rotate'])->name('cognito.rotate');
    Route::post('cognito/sources/{source}/capture', [CognitoController::class, 'capture'])->name('cognito.capture');
    Route::get('cognito/receipts/{receipt}', [CognitoController::class, 'receipt'])->name('cognito.receipts.show');
    Route::post('cognito/receipts/{receipt}/assign', [CognitoController::class, 'assign'])->name('cognito.receipts.assign');
    Route::post('cognito/receipts/{receipt}/replay', [CognitoController::class, 'replay'])->name('cognito.receipts.replay');
    Route::post('cognito/receipts/{receipt}/discard', [CognitoController::class, 'discard'])->name('cognito.receipts.discard');
    Route::post('{type}/check', [IntegrationsController::class, 'test'])->whereIn('type', ['email', 'whatsapp_official', 'whatsapp_waha', 'cognito'])->middleware('throttle:12,1')->name('check');
});
