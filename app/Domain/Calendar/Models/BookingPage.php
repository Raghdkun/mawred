<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Models;

use App\Domain\Access\Models\User;
use App\Domain\Audit\Concerns\Auditable;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * Bookable time (D77): weekly windows in the owner's timezone, cut into slots of a fixed length,
 * for everyone, a branch or a department. Candidate booking pages reuse it in Phase 6.
 *
 * @property int $id
 * @property string $ulid
 * @property int $owner_user_id
 * @property array<string, string>|string $title
 * @property array<string, string>|string|null $description
 * @property string|null $location
 * @property int $duration_minutes
 * @property int $buffer_minutes
 * @property list<array{weekday: int, from: string, to: string}> $windows
 * @property string $timezone
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property string $audience everyone|branch|department
 * @property int|null $branch_id
 * @property int|null $department_id
 * @property bool $is_active
 */
#[Guarded(['id', 'ulid'])]
final class BookingPage extends Model
{
    use Auditable;
    use HasPublicUlid;
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['title', 'description'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return HasMany<CalendarEvent, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['windows' => 'array', 'starts_on' => 'immutable_date', 'ends_on' => 'immutable_date', 'is_active' => 'boolean', 'duration_minutes' => 'integer', 'buffer_minutes' => 'integer'];
    }
}
