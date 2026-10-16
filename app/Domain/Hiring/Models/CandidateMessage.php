<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Models;

use App\Domain\Access\Models\User;
use App\Domain\Hiring\Enums\MessageChannel;
use App\Domain\Hiring\Enums\MessageDirection;
use App\Domain\Hiring\Enums\MessagePurpose;
use App\Domain\Hiring\Enums\MessageStatus;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The communications log (D94): every email and WhatsApp message to a candidate, and WhatsApp
 * replies from their number. The text is encrypted at rest.
 *
 * @property int $id
 * @property string $ulid
 * @property int $candidate_id
 * @property int|null $job_application_id
 * @property MessageDirection $direction
 * @property MessageChannel $channel
 * @property MessagePurpose $purpose
 * @property string|null $subject
 * @property string $body
 * @property MessageStatus $status
 * @property string|null $error
 * @property int|null $sent_by_user_id
 * @property CarbonImmutable $created_at
 */
#[Guarded(['id', 'ulid'])]
final class CandidateMessage extends Model
{
    use HasPublicUlid;

    public const null UPDATED_AT = null;

    /**
     * @return BelongsTo<Candidate, $this>
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => MessageDirection::class,
            'channel' => MessageChannel::class,
            'purpose' => MessagePurpose::class,
            'status' => MessageStatus::class,
            'body' => 'encrypted',
            'created_at' => 'immutable_datetime',
        ];
    }
}
