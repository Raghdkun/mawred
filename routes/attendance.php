<?php

declare(strict_types=1);

use App\Http\Controllers\Public\KioskController;
use App\Http\Controllers\Web\Attendance\BoardController;
use App\Http\Controllers\Web\Attendance\ClockController;
use App\Http\Controllers\Web\Attendance\KiosksController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('attendance')->name('attendance.')->group(function (): void {
    Route::get('/', ClockController::class)->name('clock');
    Route::get('board', [BoardController::class, 'show'])->name('board');
    Route::post('{employee}/corrections', [BoardController::class, 'correct'])->name('correct');
    Route::get('export', [BoardController::class, 'export'])->name('export');
    Route::get('kiosks', [KiosksController::class, 'index'])->name('kiosks.index');
    Route::post('kiosks', [KiosksController::class, 'store'])->name('kiosks.store');
    Route::post('kiosks/{kiosk}/relink', [KiosksController::class, 'relink'])->name('kiosks.relink');
    Route::delete('kiosks/{kiosk}', [KiosksController::class, 'destroy'])->name('kiosks.destroy');
});

// Kiosk screens (D70): no sign-in; the secret link identifies the device.
Route::prefix('kiosk')->name('kiosk.')->middleware('throttle:120,1')->group(function (): void {
    Route::get('{token}', [KioskController::class, 'show'])->name('show');
    Route::get('{token}/code', [KioskController::class, 'code'])->name('code');
});
