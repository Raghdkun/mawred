<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Models;

use App\Domain\Access\Models\User;
use App\Domain\Audit\Concerns\Auditable;
use App\Domain\Calendar\Models\CalendarEvent;
use App\Domain\Hiring\Enums\InterviewMode;
use App\Domain\Hiring\Enums\InterviewStatus;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An interview for one application with its panel (D92). It waits for the candidate to pick a
 * time from the panel's free time (D89), or is booked straight onto the calendar.
 *
 * @property int $id
 * @property string $ulid
 * @property int $job_application_id
 * @property string $title
 * @property int $duration_minutes
 * @property InterviewMode $mode
 * @property string|null $location
 * @property string $timezone
 * @property InterviewStatus $status
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property int|null $calendar_event_id
 * @property CarbonImmutable|null $booking_sent_at
 * @property int|null $created_by_user_id
 */
#[Guarded(['id', 'ulid'])]
final class Interview extends Model
{
    use Auditable;
    use HasPublicUlid;

    /** How long a booking link stays open, in days. */
    public const int BOOKING_DAYS = 14;

    /**
     * @return BelongsTo<JobApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function panel(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'interview_panelists')->withTimestamps();
    }

    /**
     * @return HasMany<Scorecard, $this>
     */
    public function scorecards(): HasMany
    {
        return $this->hasMany(Scorecard::class);
    }

    /**
     * @return BelongsTo<CalendarEvent, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class, 'calendar_event_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function bookingOpen(CarbonImmutable $now): bool
    {
        return $this->status === InterviewStatus::AwaitingBooking
            && $this->booking_sent_at instanceof CarbonImmutable
            && $this->booking_sent_at->addDays(self::BOOKING_DAYS)->isAfter($now);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => InterviewMode::class,
            'status' => InterviewStatus::class,
            'duration_minutes' => 'integer',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'booking_sent_at' => 'immutable_datetime',
        ];
    }
}
