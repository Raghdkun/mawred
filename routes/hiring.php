<?php

declare(strict_types=1);

use App\Http\Controllers\Public\CareersController;
use App\Http\Controllers\Web\Hiring\BoardController;
use App\Http\Controllers\Web\Hiring\CandidatesController;
use App\Http\Controllers\Web\Hiring\PostingsController;
use App\Http\Controllers\Web\Hiring\RequisitionsController;
use App\Http\Controllers\Web\Hiring\SettingsController;
use App\Http\Middleware\CareersLocale;
use Illuminate\Support\Facades\Route;

/*
| Hiring (Phase 6a, D80–D87).
*/
Route::middleware(['auth', 'verified'])->prefix('hiring')->group(function (): void {
    Route::get('/', fn () => to_route('hiring.requisitions.index'))->name('hiring.index');

    Route::get('requisitions', [RequisitionsController::class, 'index'])->name('hiring.requisitions.index');
    Route::get('requisitions/new', [RequisitionsController::class, 'create'])->name('hiring.requisitions.create');
    Route::post('requisitions', [RequisitionsController::class, 'store'])->name('hiring.requisitions.store');
    Route::put('requisitions/{requisition}/status', [RequisitionsController::class, 'status'])->name('hiring.requisitions.status');
    Route::put('requisitions/{requisition}/team', [RequisitionsController::class, 'team'])->name('hiring.requisitions.team');

    Route::get('requisitions/{requisition}', [BoardController::class, 'show'])->name('hiring.board');
    Route::put('requisitions/{requisition}/stages', [BoardController::class, 'stages'])->name('hiring.board.stages');
    Route::post('requisitions/{requisition}/candidates', [BoardController::class, 'store'])->name('hiring.board.add');
    Route::post('requisitions/{requisition}/uploads', [BoardController::class, 'upload'])->middleware('throttle:30,1')->name('hiring.board.uploads');
    Route::post('applications/reject', [BoardController::class, 'reject'])->name('hiring.applications.reject');
    Route::post('applications/{application}/move', [BoardController::class, 'move'])->name('hiring.applications.move');
    Route::post('applications/{application}/reopen', [BoardController::class, 'reopen'])->name('hiring.applications.reopen');
    Route::post('applications/{application}/notes', [BoardController::class, 'note'])->name('hiring.applications.notes');
    Route::get('applications/{application}/files/{upload}', [CandidatesController::class, 'file'])->name('hiring.applications.files');

    Route::get('requisitions/{requisition}/postings/new', [PostingsController::class, 'create'])->name('hiring.postings.create');
    Route::post('requisitions/{requisition}/postings', [PostingsController::class, 'store'])->name('hiring.postings.store');
    Route::get('postings/{posting}', [PostingsController::class, 'edit'])->name('hiring.postings.edit');
    Route::put('postings/{posting}', [PostingsController::class, 'update'])->name('hiring.postings.update');
    Route::put('postings/{posting}/status', [PostingsController::class, 'status'])->name('hiring.postings.status');

    Route::get('candidates', [CandidatesController::class, 'index'])->name('hiring.candidates.index');
    Route::get('candidates/{candidate}', [CandidatesController::class, 'show'])->name('hiring.candidates.show');
    Route::put('candidates/{candidate}/tags', [CandidatesController::class, 'tags'])->name('hiring.candidates.tags');
    Route::put('candidates/{candidate}/pool', [CandidatesController::class, 'pool'])->name('hiring.candidates.pool');
    Route::post('candidates/{candidate}/merge', [CandidatesController::class, 'merge'])->name('hiring.candidates.merge');
    Route::post('candidates/{candidate}/not-duplicate', [CandidatesController::class, 'notDuplicate'])->name('hiring.candidates.not-duplicate');
    Route::post('candidates/{candidate}/applications', [CandidatesController::class, 'addToRequisition'])->name('hiring.candidates.add');

    Route::get('settings', [SettingsController::class, 'show'])->name('hiring.settings');
    Route::put('settings', [SettingsController::class, 'update'])->name('hiring.settings.update');
    Route::put('settings/pipeline', [SettingsController::class, 'pipeline'])->name('hiring.settings.pipeline');
});

/*
| The public careers site (D87): no sign-in; on CAREERS_HOST when it has its own host.
*/
$careers = Route::middleware(CareersLocale::class)->prefix('careers')->name('careers.');
$host = config('mawred.careers_host');

if (is_string($host) && $host !== '') {
    $careers->domain($host);
}

$careers->group(function (): void {
    Route::get('/', [CareersController::class, 'index'])->middleware('throttle:120,1')->name('index');
    Route::get('sitemap.xml', [CareersController::class, 'sitemap'])->middleware('throttle:30,1')->name('sitemap');
    Route::get('{slug}', [CareersController::class, 'show'])->middleware('throttle:120,1')->name('show');
    Route::get('{slug}/challenge', [CareersController::class, 'challenge'])->middleware('throttle:30,1')->name('challenge');
    Route::post('{slug}/uploads', [CareersController::class, 'upload'])->middleware('throttle:20,1')->name('uploads');
    Route::post('{slug}', [CareersController::class, 'apply'])->middleware('throttle:10,1')->name('apply');
});
