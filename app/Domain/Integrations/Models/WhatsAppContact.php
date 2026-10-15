<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Models;

use App\Domain\Access\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person's WhatsApp number (D58). Only a verified number receives messages; `last_inbound_at`
 * tracks Meta's 24-hour customer-service window for free-form and interactive messages.
 *
 * @property int $id
 * @property int $user_id
 * @property string $phone_e164
 * @property CarbonImmutable|null $verified_at
 * @property string|null $code_hash
 * @property CarbonImmutable|null $code_expires_at
 * @property int $code_attempts
 * @property CarbonImmutable|null $last_inbound_at
 */
#[Guarded(['id'])]
#[Hidden(['code_hash'])]
#[Table(name: 'whatsapp_contacts')]
final class WhatsAppContact extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function inServiceWindow(): bool
    {
        return $this->last_inbound_at !== null && $this->last_inbound_at->greaterThan(now()->subHours(24));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['verified_at' => 'immutable_datetime', 'code_expires_at' => 'immutable_datetime', 'last_inbound_at' => 'immutable_datetime'];
    }
}
