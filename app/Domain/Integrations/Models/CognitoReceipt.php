<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Models;

use App\Domain\Integrations\Enums\ReceiptStatus;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payload Cognito posted, kept raw (encrypted) whatever happens to it, so it can be
 * replayed. Lives in a monthly-partitioned table (ADR-0018).
 *
 * @property int $id
 * @property string $ulid
 * @property int $cognito_source_id
 * @property string $event
 * @property string|null $entry_id
 * @property string|null $entry_version
 * @property string $idempotency_key
 * @property array<string, mixed> $payload
 * @property ReceiptStatus $status
 * @property string|null $error
 * @property int|null $request_id
 * @property int|null $employee_id
 * @property int $attempts
 * @property CarbonImmutable|null $processed_at
 * @property CarbonImmutable $created_at
 */
#[Guarded(['id', 'ulid'])]
#[Hidden(['payload'])]
final class CognitoReceipt extends Model
{
    use HasPublicUlid;

    /**
     * @return BelongsTo<CognitoSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(CognitoSource::class, 'cognito_source_id')->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'status' => ReceiptStatus::class,
            'processed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
