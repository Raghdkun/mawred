<?php

declare(strict_types=1);

use App\Http\Controllers\Web\People\InvitationController;
use Illuminate\Support\Facades\Route;

/*
| People (handoff screens 7–9). Invitation links are public but single-use and throttled.
*/
Route::middleware(['guest', 'throttle:20,1'])->group(function (): void {
    Route::get('invite/{token}', [InvitationController::class, 'show'])->name('invitations.show');
    Route::post('invite/{token}', [InvitationController::class, 'store'])->middleware('throttle:6,1')->name('invitations.accept');
});
