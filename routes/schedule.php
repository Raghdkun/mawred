<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Schedule\MyScheduleController;
use App\Http\Controllers\Web\Schedule\RosterController;
use App\Http\Controllers\Web\Schedule\ScheduleSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('schedule', [MyScheduleController::class, 'show'])->name('schedule.mine');
    Route::post('schedule/open-shifts/{shift}/claim', [MyScheduleController::class, 'claim'])->name('schedule.claim');

    Route::get('roster', [RosterController::class, 'show'])->name('roster.show');
    Route::post('roster/{branch}/shifts', [RosterController::class, 'store'])->name('roster.shifts.store');
    Route::put('roster/shifts/{shift}', [RosterController::class, 'update'])->name('roster.shifts.update');
    Route::delete('roster/shifts/{shift}', [RosterController::class, 'destroy'])->name('roster.shifts.destroy');
    Route::post('roster/{branch}/publish', [RosterController::class, 'publish'])->name('roster.publish');
    Route::post('roster/{branch}/copy', [RosterController::class, 'copy'])->name('roster.copy');
    Route::post('roster/{branch}/rotation', [RosterController::class, 'rotation'])->name('roster.rotation');
    Route::put('roster/{branch}/coverage', [RosterController::class, 'coverage'])->name('roster.coverage');
    Route::post('roster/claims/{claim}/assign', [RosterController::class, 'assign'])->name('roster.claims.assign');

    Route::get('schedule/settings', [ScheduleSettingsController::class, 'index'])->name('schedule.settings');
    Route::post('schedule/settings/templates', [ScheduleSettingsController::class, 'storeTemplate'])->name('schedule.templates.store');
    Route::put('schedule/settings/templates/{template}', [ScheduleSettingsController::class, 'updateTemplate'])->name('schedule.templates.update');
    Route::delete('schedule/settings/templates/{template}', [ScheduleSettingsController::class, 'destroyTemplate'])->name('schedule.templates.destroy');
    Route::post('schedule/settings/rotations', [ScheduleSettingsController::class, 'saveRotation'])->name('schedule.rotations.store');
    Route::put('schedule/settings/rotations/{rotation}', [ScheduleSettingsController::class, 'saveRotation'])->name('schedule.rotations.update');
});
