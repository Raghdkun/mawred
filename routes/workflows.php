<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Workflows\StuckWorkflowsController;
use App\Http\Controllers\Web\Workflows\WorkflowsController;
use Illuminate\Support\Facades\Route;

/*
| Workflows (handoff 15): list, builder, simulator, and the dead-letter view (ADR-0009).
*/
Route::middleware(['auth', 'verified'])->prefix('workflows')->name('workflows.')->group(function (): void {
    Route::get('/', [WorkflowsController::class, 'index'])->name('index');
    Route::post('/', [WorkflowsController::class, 'store'])->name('store');
    Route::get('stuck', [StuckWorkflowsController::class, 'index'])->name('stuck.index');
    Route::post('stuck/steps/{step}/retry', [StuckWorkflowsController::class, 'retry'])->name('stuck.retry');
    Route::post('stuck/steps/{step}/skip', [StuckWorkflowsController::class, 'skip'])->name('stuck.skip');
    Route::post('stuck/{instance}/cancel', [StuckWorkflowsController::class, 'cancel'])->name('stuck.cancel');
    Route::get('{workflow}', [WorkflowsController::class, 'builder'])->name('builder');
    Route::put('{workflow}/draft', [WorkflowsController::class, 'saveDraft'])->name('draft');
    Route::delete('{workflow}/draft', [WorkflowsController::class, 'discard'])->name('discard');
    Route::post('{workflow}/publish', [WorkflowsController::class, 'publish'])->name('publish');
    Route::post('{workflow}/simulate', [WorkflowsController::class, 'simulate'])->middleware('throttle:30,1')->name('simulate');
});
