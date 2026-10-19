<?php

declare(strict_types=1);

namespace App\Domain\Training\Models;

use App\Domain\Access\Models\User;
use App\Domain\Audit\Concerns\Auditable;
use App\Domain\Training\Enums\BookingStatus;
use App\Domain\Training\Enums\SessionMode;
use App\Domain\Training\Enums\SessionStatus;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An instructor-led session of a course (D98): when and where, how many seats, and the trainer.
 * Sessions are alternatives (D108); the trainer shows a rotating QR to check people in (D101).
 *
 * @property int $id
 * @property string $ulid
 * @property int $course_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property string $timezone
 * @property SessionMode $mode
 * @property string|null $location
 * @property string|null $meeting_url
 * @property int|null $capacity
 * @property int|null $trainer_user_id
 * @property int|null $calendar_event_id
 * @property string $checkin_secret
 * @property SessionStatus $status
 */
#[Guarded(['id', 'ulid'])]
#[Hidden(['checkin_secret'])]
final class TrainingSession extends Model
{
    use Auditable;
    use HasPublicUlid;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'scheduled', 'mode' => 'onsite'];

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_user_id');
    }

    /**
     * @return HasMany<SessionBooking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(SessionBooking::class);
    }

    public function seatsTaken(): int
    {
        return $this->bookings()->where('status', BookingStatus::Booked->value)->count();
    }

    public function isFull(): bool
    {
        return $this->capacity !== null && $this->seatsTaken() >= $this->capacity;
    }

    /**
     * @return list<string>
     */
    protected function auditExcept(): array
    {
        return ['checkin_secret'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'mode' => SessionMode::class,
            'status' => SessionStatus::class,
            'checkin_secret' => 'encrypted',
        ];
    }
}
