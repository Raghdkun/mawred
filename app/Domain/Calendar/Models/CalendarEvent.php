<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Models;

use App\Domain\Access\Models\User;
use App\Domain\Audit\Concerns\Auditable;
use App\Domain\Calendar\Enums\EventKind;
use App\Domain\Calendar\Enums\Visibility;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A meeting or booking. A repeating series keeps its rule here (D78), skipped occurrences in
 * `exdates`, and an override is a separate event pointing at the series with the occurrence it
 * replaces (`original_start`). `sequence` counts changes for `.ics` updates.
 *
 * @property int $id
 * @property string $ulid
 * @property EventKind $kind
 * @property string $title
 * @property string|null $description
 * @property string|null $location
 * @property int $organizer_user_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property string $timezone
 * @property Visibility $visibility
 * @property array{freq: string, interval?: int, byweekday?: list<int>, until?: string|null, count?: int|null}|null $recurrence
 * @property list<string>|null $exdates ISO 8601 UTC starts of skipped occurrences
 * @property int|null $series_id
 * @property CarbonImmutable|null $original_start
 * @property int|null $booking_page_id
 * @property int $sequence
 * @property CarbonImmutable|null $cancelled_at
 */
#[Guarded(['id', 'ulid'])]
final class CalendarEvent extends Model
{
    use Auditable;
    use HasPublicUlid;

    /**
     * @return BelongsTo<User, $this>
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_user_id');
    }

    /**
     * @return HasMany<EventParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(EventParticipant::class);
    }

    /**
     * @return BelongsTo<CalendarEvent, $this>
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(self::class, 'series_id');
    }

    /**
     * @return HasMany<CalendarEvent, $this>
     */
    public function overrides(): HasMany
    {
        return $this->hasMany(self::class, 'series_id');
    }

    /**
     * @return BelongsTo<BookingPage, $this>
     */
    public function bookingPage(): BelongsTo
    {
        return $this->belongsTo(BookingPage::class);
    }

    public function durationSeconds(): int
    {
        return $this->ends_at->getTimestamp() - $this->starts_at->getTimestamp();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => EventKind::class,
            'visibility' => Visibility::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'original_start' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'recurrence' => 'array',
            'exdates' => 'array',
            'sequence' => 'integer',
        ];
    }
}
