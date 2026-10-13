<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Setup\OwnerClaimController;
use App\Http\Controllers\Web\Setup\SetupWizardController;
use Illuminate\Support\Facades\Route;

/*
| First-run setup (brief §4). The claim link comes from `php artisan mawred:install`.
*/
Route::middleware(['guest', 'throttle:20,1'])->group(function (): void {
    Route::get('setup/claim/{token}', [OwnerClaimController::class, 'show'])->name('setup.claim.show');
    Route::post('setup/claim/{token}', [OwnerClaimController::class, 'store'])->middleware('throttle:6,1')->name('setup.claim.store');
});

Route::middleware(['auth', 'verified'])->prefix('setup')->name('setup.')->group(function (): void {
    Route::post('steps/company', [SetupWizardController::class, 'saveCompany'])->name('steps.company');
    Route::post('steps/languages', [SetupWizardController::class, 'saveLanguages'])->name('steps.languages');
    Route::post('steps/{step}/complete', [SetupWizardController::class, 'complete'])->name('steps.complete');
    Route::post('steps/{step}/skip', [SetupWizardController::class, 'skip'])->name('steps.skip');
    Route::post('finish', [SetupWizardController::class, 'finish'])->name('finish');
    Route::get('{step?}', [SetupWizardController::class, 'show'])->name('show');
});
