<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Organization\BranchesController;
use App\Http\Controllers\Web\Organization\CatalogsController;
use App\Http\Controllers\Web\Organization\DepartmentsController;
use App\Http\Controllers\Web\Organization\PositionsController;
use Illuminate\Support\Facades\Route;

/*
| Organization (handoff screen 39). Reference data: gated by organization.* with no data scope.
*/
Route::middleware(['auth', 'verified'])->prefix('organization')->name('organization.')->group(function (): void {
    Route::get('/', fn () => to_route('organization.branches.index'))->name('index');

    Route::get('branches', [BranchesController::class, 'index'])->name('branches.index');
    Route::post('branches', [BranchesController::class, 'store'])->name('branches.store');
    Route::get('branches/{branch}', [BranchesController::class, 'show'])->name('branches.show');
    Route::put('branches/{branch}', [BranchesController::class, 'update'])->name('branches.update');
    Route::delete('branches/{branch}', [BranchesController::class, 'destroy'])->name('branches.destroy');

    Route::get('departments', [DepartmentsController::class, 'index'])->name('departments.index');
    Route::post('departments', [DepartmentsController::class, 'store'])->name('departments.store');
    Route::put('departments/{department}', [DepartmentsController::class, 'update'])->name('departments.update');
    Route::patch('departments/{department}/move', [DepartmentsController::class, 'move'])->name('departments.move');
    Route::delete('departments/{department}', [DepartmentsController::class, 'destroy'])->name('departments.destroy');

    Route::get('positions', [PositionsController::class, 'index'])->name('positions.index');
    Route::post('positions', [PositionsController::class, 'store'])->name('positions.store');
    Route::put('positions/{position}', [PositionsController::class, 'update'])->name('positions.update');
    Route::delete('positions/{position}', [PositionsController::class, 'destroy'])->name('positions.destroy');

    Route::get('{kind}', [CatalogsController::class, 'index'])->name('catalogs.index');
    Route::post('{kind}', [CatalogsController::class, 'store'])->name('catalogs.store');
    Route::put('{kind}/{entry}', [CatalogsController::class, 'update'])->name('catalogs.update');
    Route::delete('{kind}/{entry}', [CatalogsController::class, 'destroy'])->name('catalogs.destroy');
});
