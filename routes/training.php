<?php

declare(strict_types=1);

use App\Http\Controllers\Public\CertificateVerifyController;
use App\Http\Controllers\Web\Training\CatalogController;
use App\Http\Controllers\Web\Training\LearnController;
use App\Http\Controllers\Web\Training\Manage\CoursesController;
use App\Http\Controllers\Web\Training\Manage\PathsController;
use App\Http\Controllers\Web\Training\Manage\SessionsController as ManageSessionsController;
use App\Http\Controllers\Web\Training\MyLearningController;
use App\Http\Controllers\Web\Training\RecordsController;
use App\Http\Controllers\Web\Training\SessionsController;
use Illuminate\Support\Facades\Route;

/*
| Training (Phase 7, D98–D110).
*/

// The public certificate check (D104): anyone with the serial or the QR.
Route::get('verify/{serial}', CertificateVerifyController::class)->middleware('throttle:60,1')->where('serial', '[A-Za-z0-9-]{6,24}')->name('certificates.verify');

Route::middleware(['auth', 'verified'])->prefix('training')->group(function (): void {
    Route::get('/', [CatalogController::class, 'index'])->name('training.catalog');
    Route::get('my', MyLearningController::class)->name('training.my');

    Route::get('courses/{course}', [CatalogController::class, 'show'])->name('training.courses.show');
    Route::post('courses/{course}/enrol', [CatalogController::class, 'enrol'])->name('training.courses.enrol');
    Route::get('paths/{path}', [CatalogController::class, 'path'])->name('training.paths.show');
    Route::post('paths/{path}/join', [CatalogController::class, 'join'])->name('training.paths.join');
    Route::delete('enrollments/{enrollment}', [CatalogController::class, 'leave'])->name('training.enrollments.cancel');

    Route::get('courses/{course}/learn/{module}', [LearnController::class, 'show'])->name('training.learn');
    Route::post('courses/{course}/learn/{module}/done', [LearnController::class, 'complete'])->name('training.learn.complete');
    Route::post('courses/{course}/learn/{module}/attempts', [LearnController::class, 'attempt'])->name('training.learn.attempt');
    Route::get('courses/{course}/learn/{module}/file', [LearnController::class, 'file'])->name('training.learn.file');

    Route::post('courses/{course}/sessions/{session}/book', [SessionsController::class, 'book'])->name('training.sessions.book');
    Route::delete('bookings/{booking}', [SessionsController::class, 'cancel'])->name('training.bookings.cancel');
    Route::get('check-in', [SessionsController::class, 'checkIn'])->name('training.check-in');
    Route::post('check-in', [SessionsController::class, 'confirmCheckIn'])->middleware('throttle:30,1')->name('training.check-in.confirm');
    Route::post('enrollments/{enrollment}/feedback', [SessionsController::class, 'feedback'])->name('training.feedback');

    Route::get('certificates/{certificate}/pdf', [RecordsController::class, 'certificate'])->name('training.certificates.pdf');
    Route::post('certificates/{certificate}/revoke', [RecordsController::class, 'revoke'])->name('training.certificates.revoke');
    Route::post('external/{record}/certificate', [RecordsController::class, 'upload'])->name('training.external.upload');
    Route::get('external/{record}/certificate', [RecordsController::class, 'download'])->name('training.external.certificate');
    Route::post('external/{record}/enrol', [RecordsController::class, 'link'])->name('training.external.link');

    Route::prefix('manage')->name('training.manage.')->group(function (): void {
        Route::get('courses', [CoursesController::class, 'index'])->name('courses.index');
        Route::get('courses/new', [CoursesController::class, 'create'])->name('courses.create');
        Route::post('courses', [CoursesController::class, 'store'])->name('courses.store');
        Route::get('courses/{course}', [CoursesController::class, 'edit'])->name('courses.edit');
        Route::put('courses/{course}', [CoursesController::class, 'update'])->name('courses.update');
        Route::put('courses/{course}/status', [CoursesController::class, 'status'])->name('courses.status');
        Route::put('courses/{course}/modules', [CoursesController::class, 'modules'])->name('courses.modules');
        Route::post('courses/{course}/modules/{module}/file', [CoursesController::class, 'file'])->name('courses.file');
        Route::get('courses/{course}/people', [CoursesController::class, 'people'])->name('courses.people');
        Route::post('courses/{course}/assign', [CoursesController::class, 'assign'])->name('courses.assign');
        Route::post('enrollments/{enrollment}/modules/{module}/reset', [CoursesController::class, 'resetQuiz'])->name('enrollments.reset');

        Route::post('courses/{course}/sessions', [ManageSessionsController::class, 'store'])->name('sessions.store');
        Route::get('sessions/{session}', [ManageSessionsController::class, 'show'])->name('sessions.show');
        Route::put('sessions/{session}', [ManageSessionsController::class, 'update'])->name('sessions.update');
        Route::delete('sessions/{session}', [ManageSessionsController::class, 'cancel'])->name('sessions.cancel');
        Route::get('sessions/{session}/check-in', [ManageSessionsController::class, 'display'])->name('sessions.display');
        Route::get('sessions/{session}/code', [ManageSessionsController::class, 'code'])->name('sessions.code');
        Route::put('bookings/{booking}/attendance', [ManageSessionsController::class, 'attendance'])->name('bookings.attendance');

        Route::get('paths', [PathsController::class, 'index'])->name('paths.index');
        Route::post('paths', [PathsController::class, 'store'])->name('paths.store');
        Route::put('paths/{path}', [PathsController::class, 'update'])->name('paths.update');
        Route::put('paths/{path}/status', [PathsController::class, 'status'])->name('paths.status');
    });
});
