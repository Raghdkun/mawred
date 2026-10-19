<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Training\CatalogController;
use App\Http\Controllers\Web\Training\LearnController;
use App\Http\Controllers\Web\Training\Manage\CoursesController;
use App\Http\Controllers\Web\Training\Manage\PathsController;
use App\Http\Controllers\Web\Training\MyLearningController;
use Illuminate\Support\Facades\Route;

/*
| Training (Phase 7a, D98–D102).
*/
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

        Route::get('paths', [PathsController::class, 'index'])->name('paths.index');
        Route::post('paths', [PathsController::class, 'store'])->name('paths.store');
        Route::put('paths/{path}', [PathsController::class, 'update'])->name('paths.update');
        Route::put('paths/{path}/status', [PathsController::class, 'status'])->name('paths.status');
    });
});
