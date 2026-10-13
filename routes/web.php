<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Account\LocaleController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

// Role-based home (handoff screens 4–6) replaces this redirect once the shell lands.
Route::get('/', fn (): RedirectResponse => to_route('dashboard'))->middleware('auth')->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', fn (): Response => Inertia::render('home/index'))->name('dashboard');
});

Route::patch('locale', [LocaleController::class, 'update'])->middleware('throttle:30,1')->name('locale.update');

require __DIR__.'/settings.php';
require __DIR__.'/setup.php';
require __DIR__.'/access.php';
require __DIR__.'/audit.php';
require __DIR__.'/notifications.php';
