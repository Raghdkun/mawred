<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Domain\Organization\Models\Branch;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A screen at a branch showing rotating QR codes that people scan to clock in (D70). The device
 * opens it with a secret link (only its hash is stored); the QR codes are HMACs of the time slot.
 *
 * @property int $id
 * @property string $ulid
 * @property int $branch_id
 * @property string $name
 * @property string $secret
 * @property string $token_hash
 * @property CarbonImmutable|null $last_seen_at
 * @property bool $is_active
 */
#[Guarded(['id', 'ulid'])]
#[Hidden(['secret', 'token_hash'])]
final class Kiosk extends Model
{
    use Auditable;
    use HasPublicUlid;

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    /**
     * @return list<string>
     */
    protected function auditExcept(): array
    {
        return ['secret', 'token_hash', 'last_seen_at'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'last_seen_at' => 'immutable_datetime', 'is_active' => 'boolean'];
    }
}
