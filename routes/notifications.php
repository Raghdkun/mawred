<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Notifications\NotificationsController;
use App\Http\Controllers\Web\Notifications\NotificationTemplatesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('notifications')->name('notifications.')->group(function (): void {
    Route::get('/', [NotificationsController::class, 'index'])->name('index');
    Route::get('preferences', [NotificationsController::class, 'preferences'])->name('preferences');
    Route::put('preferences', [NotificationsController::class, 'updatePreferences'])->name('preferences.update');
    Route::get('templates', [NotificationTemplatesController::class, 'index'])->name('templates.index');
    Route::put('templates/{event}/{channel}', [NotificationTemplatesController::class, 'update'])->where(['event' => '[a-z_.]+', 'channel' => 'in_app|email'])->name('templates.update');
    Route::delete('templates/{event}/{channel}', [NotificationTemplatesController::class, 'destroy'])->where(['event' => '[a-z_.]+', 'channel' => 'in_app|email'])->name('templates.destroy');
    Route::post('read-all', [NotificationsController::class, 'markAllRead'])->name('read-all');
    Route::post('{notification}/read', [NotificationsController::class, 'markRead'])->name('read');
});
