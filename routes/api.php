<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Access\UserSearchController;
use App\Http\Controllers\Api\V1\Organization\BranchSearchController;
use App\Http\Controllers\Api\V1\Organization\DepartmentSearchController;
use App\Http\Controllers\Api\V1\Organization\PositionSearchController;
use App\Http\Controllers\Api\V1\People\EmployeesController;
use Illuminate\Support\Facades\Route;

/*
| /api/v1 (ADR-0012). Sanctum tokens for the Flutter app and integration bots; Sanctum stateful
| cookies for the web app's async widgets. Every mutating endpoint accepts `Idempotency-Key`.
*/
Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(['auth:sanctum', 'idempotent'])
    ->group(function (): void {
        Route::get('users', UserSearchController::class)->name('users.index');

        Route::get('branches', BranchSearchController::class)->name('branches.index');
        Route::get('departments', DepartmentSearchController::class)->name('departments.index');
        Route::get('positions', PositionSearchController::class)->name('positions.index');

        Route::get('employees', [EmployeesController::class, 'index'])->name('employees.index');
        Route::get('employees/{employee}', [EmployeesController::class, 'show'])->name('employees.show');
    });
