<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Access\ImpersonationController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

// Role-based home (handoff screens 4–6) replaces this redirect once the shell lands.
Route::get('/', fn (): RedirectResponse => to_route('dashboard'))->middleware('auth')->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', fn (): Response => Inertia::render('dashboard'))->name('dashboard');

    Route::post('access/users/{user}/impersonate', [ImpersonationController::class, 'store'])->name('access.impersonate.start');
    Route::delete('access/impersonate', [ImpersonationController::class, 'destroy'])->name('access.impersonate.stop');
});

require __DIR__.'/settings.php';
require __DIR__.'/setup.php';
