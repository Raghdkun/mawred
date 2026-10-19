<?php

declare(strict_types=1);

namespace App\Domain\Training\Data;

use App\Domain\People\Models\Employee;
use App\Domain\Training\Models\SessionBooking;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Someone on a session's roster (D101): seat or waitlist, and whether and how they checked in. */
#[TypeScript]
final class RosterRowData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $status,
        public ?string $attendedAt,
        public ?string $checkIn,
    ) {}

    public static function fromBooking(SessionBooking $booking, string $locale): self
    {
        $employee = $booking->enrollment?->employee;

        return new self(
            $booking->ulid,
            $employee instanceof Employee ? $employee->displayName($locale) : '—',
            $booking->status->value,
            $booking->attended_at?->toIso8601String(),
            $booking->check_in,
        );
    }
}
