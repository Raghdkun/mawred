<?php

declare(strict_types=1);

use App\Http\Controllers\Public\ActionLinkController;
use App\Http\Controllers\Web\Requests\ApprovalsController;
use App\Http\Controllers\Web\Requests\DelegationsController;
use App\Http\Controllers\Web\Requests\MyRequestsController;
use App\Http\Controllers\Web\Requests\RequestCenterController;
use Illuminate\Support\Facades\Route;

/*
| Requests and approvals (handoff 10–13). Record access goes through RequestPolicy (requester,
| scoped HR, or anyone with a task on it); out-of-scope records answer 404.
*/
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('requests', [MyRequestsController::class, 'index'])->name('requests.index');
    Route::get('requests/new', [RequestCenterController::class, 'index'])->name('requests.center');
    Route::get('requests/new/{type}', [RequestCenterController::class, 'create'])->name('requests.create');
    Route::post('requests/new/{type}', [RequestCenterController::class, 'store'])->name('requests.store');
    Route::post('requests/new/{type}/uploads', [RequestCenterController::class, 'upload'])->middleware('throttle:30,1')->name('requests.uploads');

    Route::get('requests/{employeeRequest}', [MyRequestsController::class, 'show'])->name('requests.show');
    Route::post('requests/{employeeRequest}/resubmit', [MyRequestsController::class, 'resubmit'])->name('requests.resubmit');
    Route::post('requests/{employeeRequest}/withdraw', [MyRequestsController::class, 'withdraw'])->name('requests.withdraw');
    Route::get('requests/{employeeRequest}/files/{upload}', [MyRequestsController::class, 'download'])->name('requests.files');
    Route::post('requests/{employeeRequest}/comments', [MyRequestsController::class, 'comment'])->name('requests.comments');

    Route::get('approvals', [ApprovalsController::class, 'index'])->name('approvals.index');
    Route::get('approvals/delegations', [DelegationsController::class, 'index'])->name('delegations.index');
    Route::post('approvals/delegations', [DelegationsController::class, 'store'])->name('delegations.store');
    Route::delete('approvals/delegations/{delegation}', [DelegationsController::class, 'destroy'])->name('delegations.destroy');
    Route::post('approvals/bulk', [ApprovalsController::class, 'bulk'])->name('approvals.bulk');
    Route::post('approvals/{task}/decision', [ApprovalsController::class, 'decide'])->name('approvals.decide');
    Route::post('approvals/{task}/delegate', [ApprovalsController::class, 'delegate'])->name('approvals.delegate');
});

/*
| Link approvals (ADR-0009, D24): opening a link shows a confirmation; sensitive types need the
| assignee signed in. Rate limited; tokens are single-use and expire.
*/
Route::get('a/{token}', [ActionLinkController::class, 'show'])->middleware('throttle:30,1')->name('action-links.show');
Route::post('a/{token}', [ActionLinkController::class, 'confirm'])->middleware('throttle:10,1')->name('action-links.confirm');
