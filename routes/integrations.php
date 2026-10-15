<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Integrations\CognitoController;
use App\Http\Controllers\Web\Integrations\IntegrationsController;
use App\Http\Controllers\Web\Integrations\WhatsAppController;
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
    Route::get('whatsapp', [WhatsAppController::class, 'show'])->name('whatsapp');
    Route::post('whatsapp/official', [WhatsAppController::class, 'saveOfficial'])->name('whatsapp.official');
    Route::post('whatsapp/waha', [WhatsAppController::class, 'saveWaha'])->name('whatsapp.waha');
    Route::post('whatsapp/templates/sync', [WhatsAppController::class, 'sync'])->middleware('throttle:6,1')->name('whatsapp.sync');
    Route::post('whatsapp/waha/pair', [WhatsAppController::class, 'pair'])->middleware('throttle:10,1')->name('whatsapp.pair');
    Route::get('whatsapp/waha/pairing', [WhatsAppController::class, 'pairing'])->middleware('throttle:60,1')->name('whatsapp.pairing');
    Route::put('whatsapp/routing', [WhatsAppController::class, 'routing'])->name('whatsapp.routing');
    Route::post('whatsapp/test', [WhatsAppController::class, 'test'])->middleware('throttle:6,1')->name('whatsapp.test');
    Route::post('whatsapp/invite', [WhatsAppController::class, 'invite'])->middleware('throttle:3,1')->name('whatsapp.invite');
    Route::post('{type}/check', [IntegrationsController::class, 'test'])->whereIn('type', ['email', 'whatsapp_official', 'whatsapp_waha', 'cognito'])->middleware('throttle:12,1')->name('check');
});
