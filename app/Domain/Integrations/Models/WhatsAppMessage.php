<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Models;

use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * One send attempt over WhatsApp (ADR-0015 message log). The number is kept only as a blind
 * index and its last four digits.
 *
 * @property int $id
 * @property string $ulid
 * @property int|null $user_id
 * @property string $driver
 * @property string $purpose
 * @property string|null $phone_last4
 * @property string|null $phone_hash
 * @property string|null $template
 * @property string $status queued|sent|delivered|read|failed
 * @property string|null $provider_id
 * @property string|null $error_code
 * @property string|null $error
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $read_at
 * @property CarbonImmutable $created_at
 */
#[Guarded(['id', 'ulid'])]
#[Table(name: 'whatsapp_messages')]
final class WhatsAppMessage extends Model
{
    use HasPublicUlid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['delivered_at' => 'immutable_datetime', 'read_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }
}
