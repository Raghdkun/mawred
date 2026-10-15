<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Leave\LeaveBlackoutsController;
use App\Http\Controllers\Web\Leave\LeaveController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('leave')->name('leave.')->group(function (): void {
    Route::get('/', [LeaveController::class, 'index'])->name('index');
    Route::get('calendar', [LeaveController::class, 'calendar'])->name('calendar');
    Route::get('blackouts', [LeaveBlackoutsController::class, 'index'])->name('blackouts.index');
    Route::post('blackouts', [LeaveBlackoutsController::class, 'store'])->name('blackouts.store');
    Route::put('blackouts/{blackout}', [LeaveBlackoutsController::class, 'update'])->name('blackouts.update');
    Route::delete('blackouts/{blackout}', [LeaveBlackoutsController::class, 'destroy'])->name('blackouts.destroy');
});
