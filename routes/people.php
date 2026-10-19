<?php

declare(strict_types=1);

use App\Http\Controllers\Web\People\EmployeeDocumentsController;
use App\Http\Controllers\Web\People\EmployeeProfileController;
use App\Http\Controllers\Web\People\EmployeeRecordsController;
use App\Http\Controllers\Web\People\ImportsController;
use App\Http\Controllers\Web\People\InvitationController;
use App\Http\Controllers\Web\People\PeopleController;
use App\Http\Controllers\Web\People\PeopleSettingsController;
use App\Http\Controllers\Web\People\PlacementsController;
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

    Route::get('settings/fields', [PeopleSettingsController::class, 'fields'])->name('settings.fields');
    Route::put('settings/numbering', [PeopleSettingsController::class, 'updateNumbering'])->name('settings.numbering');
    Route::post('settings/fields', [PeopleSettingsController::class, 'storeField'])->name('settings.fields.store');
    Route::put('settings/fields/{field}', [PeopleSettingsController::class, 'updateField'])->name('settings.fields.update');
    Route::delete('settings/fields/{field}', [PeopleSettingsController::class, 'destroyField'])->name('settings.fields.destroy');
    Route::get('settings/document-types', [PeopleSettingsController::class, 'documentTypes'])->name('settings.document-types');
    Route::post('settings/document-types', [PeopleSettingsController::class, 'storeDocumentType'])->name('settings.document-types.store');
    Route::put('settings/document-types/{type}', [PeopleSettingsController::class, 'updateDocumentType'])->name('settings.document-types.update');

    Route::get('/', [PeopleController::class, 'index'])->name('index');
    Route::post('invitations', [EmployeeRecordsController::class, 'inviteMany'])->name('invitations.store');
    Route::post('/', [PeopleController::class, 'store'])->name('store');
    Route::get('{employee}', [PeopleController::class, 'show'])->name('show');
    Route::put('{employee}', [EmployeeProfileController::class, 'update'])->name('update');
    Route::put('{employee}/sensitive', [EmployeeProfileController::class, 'updateSensitive'])->name('sensitive.update');
    Route::put('{employee}/custom-fields', [EmployeeProfileController::class, 'updateCustomFields'])->name('custom-fields.update');

    Route::post('{employee}/placements', [PlacementsController::class, 'change'])->name('placements.change');
    Route::post('{employee}/secondary-placements', [PlacementsController::class, 'addSecondary'])->name('placements.secondary.store');
    Route::patch('{employee}/secondary-placements/{assignment}/end', [PlacementsController::class, 'endSecondary'])->name('placements.secondary.end');
    Route::post('{employee}/termination', [PlacementsController::class, 'terminate'])->name('termination.store');

    Route::post('{employee}/documents', [EmployeeDocumentsController::class, 'store'])->name('documents.store');
    Route::post('{employee}/documents/{document}', [EmployeeDocumentsController::class, 'update'])->name('documents.update');
    Route::delete('{employee}/documents/{document}', [EmployeeDocumentsController::class, 'destroy'])->name('documents.destroy');
    Route::get('{employee}/documents/{document}/file', [EmployeeDocumentsController::class, 'download'])->name('documents.download');

    Route::post('{employee}/salaries', [EmployeeRecordsController::class, 'storeSalary'])->name('salaries.store');
    Route::post('{employee}/allowances', [EmployeeRecordsController::class, 'storeAllowance'])->name('allowances.store');
    Route::put('{employee}/allowances/{allowance}', [EmployeeRecordsController::class, 'updateAllowance'])->name('allowances.update');
    Route::post('{employee}/assets', [EmployeeRecordsController::class, 'storeAsset'])->name('assets.store');
    Route::patch('{employee}/assets/{asset}/return', [EmployeeRecordsController::class, 'returnAsset'])->name('assets.return');
    Route::post('{employee}/invitation', [EmployeeRecordsController::class, 'invite'])->name('invitation.store');
    Route::delete('{employee}/invitation', [EmployeeRecordsController::class, 'revokeInvitation'])->name('invitation.destroy');
});
