<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Models;

use App\Domain\Access\Models\User;
use App\Domain\Calendar\Enums\ParticipantRole;
use App\Domain\Calendar\Enums\Response;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone on an event and their reply (D75). One row per series; replies cover the whole series.
 *
 * @property int $id
 * @property string $ulid
 * @property int $calendar_event_id
 * @property int $user_id
 * @property ParticipantRole $role
 * @property Response $response
 * @property CarbonImmutable|null $responded_at
 */
#[Guarded(['id', 'ulid'])]
final class EventParticipant extends Model
{
    use HasPublicUlid;

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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['role' => ParticipantRole::class, 'response' => Response::class, 'responded_at' => 'immutable_datetime'];
    }
}
