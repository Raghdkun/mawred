<?php

declare(strict_types=1);

use App\Http\Controllers\Public\CandidatePagesController;
use App\Http\Controllers\Public\CareersController;
use App\Http\Controllers\Web\Hiring\BoardController;
use App\Http\Controllers\Web\Hiring\CandidatesController;
use App\Http\Controllers\Web\Hiring\InterviewsController;
use App\Http\Controllers\Web\Hiring\OffersController;
use App\Http\Controllers\Web\Hiring\PostingsController;
use App\Http\Controllers\Web\Hiring\RequisitionsController;
use App\Http\Controllers\Web\Hiring\SettingsController;
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

    Route::put('requisitions/{requisition}/criteria', [BoardController::class, 'criteria'])->name('hiring.board.criteria');
    Route::put('stages/{stage}/automation', [BoardController::class, 'automation'])->name('hiring.stages.automation');

    Route::get('interviews', [InterviewsController::class, 'mine'])->name('hiring.interviews.index');
    Route::post('applications/{application}/interviews', [InterviewsController::class, 'store'])->name('hiring.interviews.store');
    Route::delete('interviews/{interview}', [InterviewsController::class, 'destroy'])->name('hiring.interviews.destroy');
    Route::put('interviews/{interview}/scorecard', [InterviewsController::class, 'scorecard'])->name('hiring.interviews.scorecard');

    Route::post('applications/{application}/offers', [OffersController::class, 'store'])->name('hiring.offers.store');
    Route::post('applications/{application}/offers/letter', [OffersController::class, 'letter'])->name('hiring.offers.letter');
    Route::put('offers/{offer}', [OffersController::class, 'update'])->name('hiring.offers.update');
    Route::post('offers/{offer}/submit', [OffersController::class, 'submit'])->name('hiring.offers.submit');
    Route::post('offers/{offer}/send', [OffersController::class, 'send'])->name('hiring.offers.send');
    Route::post('offers/{offer}/withdraw', [OffersController::class, 'withdraw'])->name('hiring.offers.withdraw');
    Route::get('offers/{offer}/pdf', [OffersController::class, 'pdf'])->middleware('throttle:30,1')->name('hiring.offers.pdf');
    Route::post('applications/{application}/hire', [OffersController::class, 'hire'])->name('hiring.applications.hire');

    Route::post('candidates/{candidate}/messages', [CandidatesController::class, 'message'])->middleware('throttle:30,1')->name('hiring.candidates.messages');
    Route::post('candidates/{candidate}/anonymise', [CandidatesController::class, 'anonymise'])->name('hiring.candidates.anonymise');

    Route::get('settings', [SettingsController::class, 'show'])->name('hiring.settings');
    Route::put('settings', [SettingsController::class, 'update'])->name('hiring.settings.update');
    Route::put('settings/pipeline', [SettingsController::class, 'pipeline'])->name('hiring.settings.pipeline');
    Route::put('settings/criteria', [SettingsController::class, 'criteria'])->name('hiring.settings.criteria');
});

/*
| The public careers site (D87): no sign-in; on CAREERS_HOST when it has its own host.
*/
$careers = Route::prefix('careers')->name('careers.');
$host = config('mawred.careers_host');

if (is_string($host) && $host !== '') {
    $careers->domain($host);
}

$careers->group(function (): void {
    Route::get('/', [CareersController::class, 'index'])->middleware('throttle:120,1')->name('index');

    // Signed links for candidates (D89, D83, D96); declared before {slug} so they win.
    Route::get('interviews/{token}', [CandidatePagesController::class, 'interview'])->middleware('throttle:60,1')->name('interviews.show');
    Route::post('interviews/{token}', [CandidatePagesController::class, 'book'])->middleware('throttle:10,1')->name('interviews.book');
    Route::get('offers/{token}', [CandidatePagesController::class, 'offer'])->middleware('throttle:60,1')->name('offers.show');
    Route::get('offers/{token}/pdf', [CandidatePagesController::class, 'offerPdf'])->middleware('throttle:20,1')->name('offers.pdf');
    Route::post('offers/{token}/accept', [CandidatePagesController::class, 'accept'])->middleware('throttle:10,1')->name('offers.accept');
    Route::post('offers/{token}/decline', [CandidatePagesController::class, 'decline'])->middleware('throttle:10,1')->name('offers.decline');
    Route::get('privacy/{token}', [CandidatePagesController::class, 'privacy'])->middleware('throttle:30,1')->name('privacy.show');
    Route::post('privacy/{token}', [CandidatePagesController::class, 'forget'])->middleware('throttle:10,1')->name('privacy.forget');

    Route::get('sitemap.xml', [CareersController::class, 'sitemap'])->middleware('throttle:30,1')->name('sitemap');
    Route::get('{slug}', [CareersController::class, 'show'])->middleware('throttle:120,1')->name('show');
    Route::get('{slug}/challenge', [CareersController::class, 'challenge'])->middleware('throttle:30,1')->name('challenge');
    Route::post('{slug}/uploads', [CareersController::class, 'upload'])->middleware('throttle:20,1')->name('uploads');
    Route::post('{slug}', [CareersController::class, 'apply'])->middleware('throttle:10,1')->name('apply');
});
