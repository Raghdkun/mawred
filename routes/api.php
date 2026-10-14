<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Access\UserSearchController;
use App\Http\Controllers\Api\V1\Organization\BranchSearchController;
use App\Http\Controllers\Api\V1\Organization\DepartmentSearchController;
use App\Http\Controllers\Api\V1\Organization\PositionSearchController;
use App\Http\Controllers\Api\V1\People\EmployeesController;
use App\Http\Controllers\Api\V1\Policies\EmployeePoliciesController;
use App\Http\Controllers\Api\V1\Policies\LeaveTypeSearchController;
use App\Http\Controllers\Api\V1\Requests\DelegateCandidatesController;
use App\Http\Controllers\Api\V1\Search\GlobalSearchController;
use Illuminate\Support\Facades\Route;

/*
| /api/v1 (ADR-0012). Sanctum tokens for the Flutter app and integration bots; Sanctum stateful
| cookies for the web app's async widgets. Every mutating endpoint accepts `Idempotency-Key`.
*/
Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(['auth:sanctum', 'idempotent'])
    ->group(function (): void {
        Route::get('search', GlobalSearchController::class)->middleware('throttle:120,1')->name('search');
        Route::get('users', UserSearchController::class)->name('users.index');

        Route::get('branches', BranchSearchController::class)->name('branches.index');
        Route::get('departments', DepartmentSearchController::class)->name('departments.index');
        Route::get('positions', PositionSearchController::class)->name('positions.index');
        Route::get('leave-types', LeaveTypeSearchController::class)->name('leave-types.index');
        Route::get('delegates', DelegateCandidatesController::class)->name('delegates.index');

        Route::get('employees', [EmployeesController::class, 'index'])->name('employees.index');
        Route::get('employees/{employee}', [EmployeesController::class, 'show'])->name('employees.show');
        Route::get('employees/{employee}/policies', [EmployeePoliciesController::class, 'index'])->name('employees.policies');
    });
