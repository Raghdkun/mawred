<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Notifications\NotificationsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('notifications')->name('notifications.')->group(function (): void {
    Route::get('/', [NotificationsController::class, 'index'])->name('index');
    Route::post('read-all', [NotificationsController::class, 'markAllRead'])->name('read-all');
    Route::post('{notification}/read', [NotificationsController::class, 'markRead'])->name('read');
});
