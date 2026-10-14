<?php

declare(strict_types=1);

use App\Http\Controllers\Web\People\ImportsController;
use App\Http\Controllers\Web\People\InvitationController;
use Illuminate\Support\Facades\Route;

/*
| People (handoff screens 7–9). Invitation links are public but single-use and throttled.
*/
Route::middleware(['guest', 'throttle:20,1'])->group(function (): void {
    Route::get('invite/{token}', [InvitationController::class, 'show'])->name('invitations.show');
    Route::post('invite/{token}', [InvitationController::class, 'store'])->middleware('throttle:6,1')->name('invitations.accept');
});

Route::middleware(['auth', 'verified'])->prefix('people')->name('people.')->group(function (): void {
    Route::get('imports/new', [ImportsController::class, 'create'])->name('imports.create');
    Route::get('imports/template', [ImportsController::class, 'template'])->name('imports.template');
    Route::post('imports', [ImportsController::class, 'store'])->name('imports.store');
    Route::get('imports/{import}', [ImportsController::class, 'show'])->name('imports.show');
    Route::put('imports/{import}/mapping', [ImportsController::class, 'updateMapping'])->name('imports.mapping');
    Route::post('imports/{import}/commit', [ImportsController::class, 'commit'])->name('imports.commit');
    Route::delete('imports/{import}', [ImportsController::class, 'destroy'])->name('imports.destroy');
    Route::get('imports/{import}/errors', [ImportsController::class, 'errors'])->name('imports.errors');
});
