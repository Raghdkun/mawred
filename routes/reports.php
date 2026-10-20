<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Reports\BuilderController;
use App\Http\Controllers\Web\Reports\ExportsController;
use App\Http\Controllers\Web\Reports\ReportsController;
use App\Http\Controllers\Web\Reports\SchedulesController;
use Illuminate\Support\Facades\Route;

/*
| Reports (Phase 8a, D112–D114).
*/

Route::middleware(['auth', 'verified'])->prefix('reports')->group(function (): void {
    Route::get('/', [ReportsController::class, 'index'])->name('reports.index');
    Route::get('exports', [ExportsController::class, 'index'])->name('reports.exports.index');
    Route::get('exports/{run}', [ExportsController::class, 'download'])->name('reports.exports.download');

    // Custom reports (D118, D119) and schedules (D120).
    Route::get('builder', [BuilderController::class, 'create'])->name('reports.builder.create');
    Route::get('builder/{saved}', [BuilderController::class, 'edit'])->name('reports.builder.edit');
    Route::post('builder/preview', [BuilderController::class, 'preview'])->middleware('throttle:120,1')->name('reports.builder.preview');
    Route::post('saved', [BuilderController::class, 'store'])->name('reports.saved.store');
    Route::put('saved/{saved}', [BuilderController::class, 'update'])->name('reports.saved.update');
    Route::delete('saved/{saved}', [BuilderController::class, 'destroy'])->name('reports.saved.destroy');
    Route::put('saved/{saved}/shares', [BuilderController::class, 'share'])->name('reports.saved.share');
    Route::get('schedules', [SchedulesController::class, 'index'])->name('reports.schedules.index');
    Route::post('schedules', [SchedulesController::class, 'store'])->name('reports.schedules.store');
    Route::put('schedules/{schedule}', [SchedulesController::class, 'update'])->name('reports.schedules.update');
    Route::post('schedules/{schedule}/pause', [SchedulesController::class, 'pause'])->name('reports.schedules.pause');
    Route::delete('schedules/{schedule}', [SchedulesController::class, 'destroy'])->name('reports.schedules.destroy');

    Route::get('{report}', [ReportsController::class, 'show'])->where('report', '[a-z_]+\.[a-z0-9_]+')->name('reports.show');
    Route::post('{report}/exports', [ExportsController::class, 'store'])->where('report', '[a-z_]+\.[a-z0-9_]+')->middleware('throttle:20,1')->name('reports.exports.store');
});
