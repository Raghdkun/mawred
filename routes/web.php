<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Account\LocaleController;
use App\Http\Controllers\Web\HomeController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (): RedirectResponse => to_route('dashboard'))->middleware('auth')->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', HomeController::class)->name('dashboard');
});

Route::patch('locale', [LocaleController::class, 'update'])->middleware('throttle:30,1')->name('locale.update');

require __DIR__.'/settings.php';
require __DIR__.'/setup.php';
require __DIR__.'/access.php';
require __DIR__.'/organization.php';
require __DIR__.'/people.php';
require __DIR__.'/policies.php';
require __DIR__.'/forms.php';
require __DIR__.'/requests.php';
require __DIR__.'/workflows.php';
require __DIR__.'/integrations.php';
require __DIR__.'/audit.php';
require __DIR__.'/notifications.php';
