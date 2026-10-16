<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Actions;

use App\Domain\Calendar\Models\BookingPage;

/** Stops new bookings on a page; existing bookings stay on the calendar. */
final readonly class RetireBookingPage
{
    public function handle(BookingPage $page): void
    {
        $page->forceFill(['is_active' => false])->save();
    }
}
