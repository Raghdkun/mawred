<?php

declare(strict_types=1);

use App\Http\Controllers\Public\CalendarReplyController;
use App\Http\Controllers\Web\Calendar\BookingController;
use App\Http\Controllers\Web\Calendar\CalendarController;
use App\Http\Controllers\Web\Calendar\FinderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('calendar/find', FinderController::class)->name('calendar.find');
    Route::post('calendar/events', [CalendarController::class, 'store'])->name('calendar.store');
    Route::get('calendar/events/{event}', [CalendarController::class, 'show'])->name('calendar.show');
    Route::put('calendar/events/{event}', [CalendarController::class, 'update'])->name('calendar.update');
    Route::delete('calendar/events/{event}', [CalendarController::class, 'destroy'])->name('calendar.destroy');
    Route::post('calendar/events/{event}/respond', [CalendarController::class, 'respond'])->name('calendar.respond');
    Route::get('calendar/events/{event}/ics', [CalendarController::class, 'ics'])->name('calendar.ics');

    Route::get('calendar/booking-pages', [BookingController::class, 'pages'])->name('booking-pages.index');
    Route::post('calendar/booking-pages', [BookingController::class, 'store'])->name('booking-pages.store');
    Route::put('calendar/booking-pages/{page}', [BookingController::class, 'update'])->name('booking-pages.update');
    Route::delete('calendar/booking-pages/{page}', [BookingController::class, 'destroy'])->name('booking-pages.destroy');
    Route::get('book', [BookingController::class, 'index'])->name('book.index');
    Route::get('book/{page}', [BookingController::class, 'show'])->name('book.show');
    Route::post('book/{page}', [BookingController::class, 'book'])->name('book.store');
});

// One-tap replies from email (D75): the signed token is the authority, so no sign-in is needed.
Route::get('calendar/reply/{token}', CalendarReplyController::class)->middleware('throttle:60,1')->name('calendar-replies.show');
