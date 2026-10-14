<?php

declare(strict_types=1);

use App\Http\Controllers\Public\PublicFormController;
use App\Http\Controllers\Web\Forms\FormBuilderController;
use App\Http\Controllers\Web\Forms\FormsController;
use App\Http\Controllers\Web\Forms\FormSubmissionsController;
use Illuminate\Support\Facades\Route;

/*
| Forms (ADR-0008, handoff 14). Company-wide configuration: forms.* with no data scope (D40).
*/
Route::middleware(['auth', 'verified'])->prefix('forms')->name('forms.')->group(function (): void {
    Route::get('/', [FormsController::class, 'index'])->name('index');
    Route::post('/', [FormsController::class, 'store'])->name('store');
    Route::put('{form}', [FormsController::class, 'update'])->name('update');
    Route::delete('{form}', [FormsController::class, 'destroy'])->name('destroy');
    Route::post('{form}/duplicate', [FormsController::class, 'duplicate'])->name('duplicate');

    Route::get('{form}/builder', [FormBuilderController::class, 'show'])->name('builder');
    Route::put('{form}/draft', [FormBuilderController::class, 'saveDraft'])->name('draft.save');
    Route::delete('{form}/draft', [FormBuilderController::class, 'discard'])->name('draft.discard');
    Route::post('{form}/publish', [FormBuilderController::class, 'publish'])->name('publish');
    Route::get('{form}/preview', [FormBuilderController::class, 'preview'])->name('preview');
    Route::post('{form}/test', [FormBuilderController::class, 'test'])->name('test');
    Route::post('{form}/uploads', [FormBuilderController::class, 'upload'])->middleware('throttle:30,1')->name('uploads');

    Route::get('{form}/submissions', [FormSubmissionsController::class, 'index'])->name('submissions.index');
    Route::get('{form}/submissions/{submission}', [FormSubmissionsController::class, 'show'])->name('submissions.show');
    Route::get('{form}/submissions/{submission}/files/{upload}', [FormSubmissionsController::class, 'download'])->name('submissions.files');
});

/*
| Public forms (D53): no sign-in; ALTCHA, honeypot, minimum fill time and per-IP limits.
*/
Route::prefix('f')->name('public-forms.')->group(function (): void {
    Route::get('{slug}', [PublicFormController::class, 'show'])->middleware('throttle:60,1')->name('show');
    Route::get('{slug}/challenge', [PublicFormController::class, 'challenge'])->middleware('throttle:30,1')->name('challenge');
    Route::post('{slug}/uploads', [PublicFormController::class, 'upload'])->middleware('throttle:20,1')->name('uploads');
    Route::post('{slug}', [PublicFormController::class, 'submit'])->middleware('throttle:10,1')->name('submit');
});
