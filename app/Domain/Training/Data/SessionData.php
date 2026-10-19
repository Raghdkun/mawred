<?php

declare(strict_types=1);

namespace App\Domain\Training\Data;

use App\Domain\Training\Enums\BookingStatus;
use App\Domain\Training\Models\SessionBooking;
use App\Domain\Training\Models\TrainingSession;
use Illuminate\Contracts\Database\Query\Builder;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A session as learners and trainers see it (D98, D107): when and where, seats left, and the
 * viewer's own booking. The meeting link shows only to people with a seat and trainers.
 *
 * @phpstan-type Mine array{id: string, status: string, attended: bool, position: int|null}
 */
#[TypeScript]
final class SessionData extends Data
{
    /**
     * @param  array{id: string, status: string, attended: bool, position: int|null}|null  $mine
     */
    public function __construct(
        public string $id,
        public string $startsAt,
        public string $endsAt,
        public string $timezone,
        public string $mode,
        public ?string $location,
        public ?string $meetingUrl,
        public ?int $capacity,
        public int $booked,
        public int $waitlisted,
        public int $attended,
        public ?string $trainer,
        public string $status,
        public ?array $mine,
    ) {}

    public static function fromSession(TrainingSession $session, ?SessionBooking $mine, bool $trainer = false): self
    {
        $totals = ['booked' => 0, 'waitlisted' => 0, 'cancelled' => 0, 'attended' => 0];

        foreach ($session->bookings()->toBase()->selectRaw('status, count(*) as n, count(attended_at) as attended')->groupBy('status')->get() as $row) {
            $status = is_string($row->status ?? null) ? $row->status : '';
            $totals[$status] = is_numeric($row->n ?? null) ? (int) $row->n : 0;
            $totals['attended'] += is_numeric($row->attended ?? null) ? (int) $row->attended : 0;
        }

        $position = null;

        if ($mine instanceof SessionBooking && $mine->status === BookingStatus::Waitlisted) {
            $position = $session->bookings()->where('status', BookingStatus::Waitlisted->value)
                ->where(fn (Builder $q) => $q->where('waitlisted_at', '<', $mine->waitlisted_at)->orWhere(fn (Builder $w) => $w->where('waitlisted_at', $mine->waitlisted_at)->where('id', '<', $mine->id)))
                ->count() + 1;
        }

        $seated = $trainer || ($mine instanceof SessionBooking && $mine->status === BookingStatus::Booked);

        return new self(
            $session->ulid,
            $session->starts_at->toIso8601String(),
            $session->ends_at->toIso8601String(),
            $session->timezone,
            $session->mode->value,
            $session->location,
            $seated ? $session->meeting_url : null,
            $session->capacity,
            $totals['booked'],
            $totals['waitlisted'],
            $totals['attended'],
            $session->trainer?->name,
            $session->status->value,
            $mine instanceof SessionBooking && $mine->status !== BookingStatus::Cancelled
                ? ['id' => $mine->ulid, 'status' => $mine->status->value, 'attended' => $mine->attended_at !== null, 'position' => $position]
                : null,
        );
    }
}
