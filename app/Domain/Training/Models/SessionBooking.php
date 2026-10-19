<?php

declare(strict_types=1);

namespace App\Domain\Training\Models;

use App\Domain\Training\Enums\BookingStatus;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A learner's seat at a session: booked or on the waitlist (D107), and whether they attended
 * (checked in by QR or marked by the trainer, D101).
 *
 * @property int $id
 * @property string $ulid
 * @property int $training_session_id
 * @property int $enrollment_id
 * @property BookingStatus $status
 * @property CarbonImmutable|null $booked_at
 * @property CarbonImmutable|null $waitlisted_at
 * @property CarbonImmutable|null $attended_at
 * @property string|null $check_in
 * @property int|null $marked_by
 */
#[Guarded(['id', 'ulid'])]
final class SessionBooking extends Model
{
    use HasPublicUlid;

    /**
     * @return BelongsTo<TrainingSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'training_session_id');
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'booked_at' => 'immutable_datetime',
            'waitlisted_at' => 'immutable_datetime',
            'attended_at' => 'immutable_datetime',
        ];
    }
}
