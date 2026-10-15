<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Schedule\MyScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('schedule', MyScheduleController::class)->name('schedule.mine');
});
