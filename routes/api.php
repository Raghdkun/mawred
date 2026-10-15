<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Access\UserSearchController;
use App\Http\Controllers\Api\V1\Leave\LeaveCalendarController;
use App\Http\Controllers\Api\V1\Leave\LeaveController;
use App\Http\Controllers\Api\V1\Organization\BranchSearchController;
use App\Http\Controllers\Api\V1\Organization\DepartmentSearchController;
use App\Http\Controllers\Api\V1\Organization\PositionSearchController;
use App\Http\Controllers\Api\V1\People\EmployeesController;
use App\Http\Controllers\Api\V1\Policies\EmployeePoliciesController;
use App\Http\Controllers\Api\V1\Policies\LeaveTypeSearchController;
use App\Http\Controllers\Api\V1\Requests\ApprovalsController;
use App\Http\Controllers\Api\V1\Requests\DelegateCandidatesController;
use App\Http\Controllers\Api\V1\Requests\RequestsController;
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

        Route::get('request-types', [RequestsController::class, 'types'])->name('request-types.index');
        Route::get('request-types/{type}', [RequestsController::class, 'type'])->name('request-types.show');
        Route::post('request-types/{type}/requests', [RequestsController::class, 'store'])->name('requests.store');
        Route::get('requests', [RequestsController::class, 'index'])->name('requests.index');
        Route::get('requests/{employeeRequest}', [RequestsController::class, 'show'])->name('requests.show');
        Route::post('requests/{employeeRequest}/withdraw', [RequestsController::class, 'withdraw'])->name('requests.withdraw');
        Route::get('approvals', [ApprovalsController::class, 'index'])->name('approvals.index');
        Route::post('approvals/{task}/decision', [ApprovalsController::class, 'decide'])->name('approvals.decide');

        Route::get('employees', [EmployeesController::class, 'index'])->name('employees.index');
        Route::get('employees/{employee}', [EmployeesController::class, 'show'])->name('employees.show');
        Route::get('employees/{employee}/policies', [EmployeePoliciesController::class, 'index'])->name('employees.policies');

        Route::post('request-types/{type}/leave-quote', [LeaveController::class, 'quote'])->middleware('throttle:120,1')->name('leave.quote');
        Route::get('employees/{employee}/leave', [LeaveController::class, 'balances'])->name('leave.balances');
        Route::get('employees/{employee}/leave/requests', [LeaveController::class, 'timeAway'])->name('leave.requests');
        Route::get('employees/{employee}/leave/{leaveType}/statement', [LeaveController::class, 'statement'])->name('leave.statement');
        Route::post('employees/{employee}/leave/adjustments', [LeaveController::class, 'adjust'])->name('leave.adjust');
        Route::post('leave/requests/{leave}/cancel', [LeaveController::class, 'cancel'])->name('leave.cancel');
        Route::get('leave/calendar', LeaveCalendarController::class)->name('leave.calendar');
    });
