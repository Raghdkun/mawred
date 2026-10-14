<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Policies\EffectivePolicyController;
use App\Http\Controllers\Web\Policies\HolidayCalendarsController;
use App\Http\Controllers\Web\Policies\LeaveTypesController;
use App\Http\Controllers\Web\Policies\PolicyAssignmentsController;
use App\Http\Controllers\Web\Policies\PolicySetsController;
use Illuminate\Support\Facades\Route;

/*
| Policies (handoff screens 16–21). Company-wide configuration: gated by policies.* with no data
| scope, except the effective policy of an employee, which also needs that employee in scope.
*/
Route::middleware(['auth', 'verified'])->prefix('policies')->name('policies.')->group(function (): void {
    Route::get('/', [PolicySetsController::class, 'index'])->name('index');
    Route::post('/', [PolicySetsController::class, 'store'])->name('store');
    Route::post('import', [PolicySetsController::class, 'import'])->name('import');

    // Fixed paths sit before {set}.
    Route::get('assignments', [PolicyAssignmentsController::class, 'index'])->name('assignments.index');
    Route::post('assignments', [PolicyAssignmentsController::class, 'store'])->name('assignments.store');
    Route::post('assignments/preview', [PolicyAssignmentsController::class, 'preview'])->name('assignments.preview');
    Route::delete('assignments/{assignment}', [PolicyAssignmentsController::class, 'destroy'])->name('assignments.destroy');

    Route::get('effective', [EffectivePolicyController::class, 'show'])->name('effective');

    Route::get('leave-types', [LeaveTypesController::class, 'index'])->name('leave-types.index');
    Route::post('leave-types', [LeaveTypesController::class, 'store'])->name('leave-types.store');
    Route::put('leave-types/{type}', [LeaveTypesController::class, 'update'])->name('leave-types.update');
    Route::delete('leave-types/{type}', [LeaveTypesController::class, 'destroy'])->name('leave-types.destroy');

    Route::get('holidays', [HolidayCalendarsController::class, 'index'])->name('holidays.index');
    Route::post('holidays', [HolidayCalendarsController::class, 'store'])->name('holidays.store');
    Route::post('holidays/presets', [HolidayCalendarsController::class, 'installPreset'])->name('holidays.presets');
    Route::put('holidays/{calendar}', [HolidayCalendarsController::class, 'update'])->name('holidays.update');
    Route::delete('holidays/{calendar}', [HolidayCalendarsController::class, 'destroy'])->name('holidays.destroy');
    Route::post('holidays/{calendar}/rules', [HolidayCalendarsController::class, 'storeRule'])->name('holidays.rules.store');
    Route::put('holidays/{calendar}/rules/{rule}', [HolidayCalendarsController::class, 'updateRule'])->name('holidays.rules.update');
    Route::delete('holidays/{calendar}/rules/{rule}', [HolidayCalendarsController::class, 'destroyRule'])->name('holidays.rules.destroy');
    Route::post('holidays/{calendar}/import', [HolidayCalendarsController::class, 'import'])->name('holidays.import');

    Route::get('{set}', [PolicySetsController::class, 'show'])->name('show');
    Route::put('{set}', [PolicySetsController::class, 'update'])->name('update');
    Route::delete('{set}', [PolicySetsController::class, 'destroy'])->name('destroy');
    Route::put('{set}/values', [PolicySetsController::class, 'updateValues'])->name('values.update');
    Route::post('{set}/preview', [PolicySetsController::class, 'preview'])->name('preview');
    Route::get('{set}/export', [PolicySetsController::class, 'export'])->name('export');
});
