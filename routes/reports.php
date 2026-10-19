<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Reports\ExportsController;
use App\Http\Controllers\Web\Reports\ReportsController;
use Illuminate\Support\Facades\Route;

/*
| Reports (Phase 8a, D112–D114).
*/

Route::middleware(['auth', 'verified'])->prefix('reports')->group(function (): void {
    Route::get('/', [ReportsController::class, 'index'])->name('reports.index');
    Route::get('exports', [ExportsController::class, 'index'])->name('reports.exports.index');
    Route::get('exports/{run}', [ExportsController::class, 'download'])->name('reports.exports.download');
    Route::get('{report}', [ReportsController::class, 'show'])->where('report', '[a-z_]+\.[a-z_]+')->name('reports.show');
    Route::post('{report}/exports', [ExportsController::class, 'store'])->where('report', '[a-z_]+\.[a-z_]+')->middleware('throttle:20,1')->name('reports.exports.store');
});
