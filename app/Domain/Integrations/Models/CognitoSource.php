<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Domain\Requests\Models\RequestType;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A Cognito form wired to Mawred (ADR-0015). Its webhook URLs carry a secret token; only the
 * token's sha256 is stored. `mapping` says which Cognito fields feed which form fields, how to
 * find the employee, and how to prefill the hosted form (D60).
 *
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $token_hash
 * @property string $token_last4
 * @property int|null $request_type_id
 * @property string|null $form_url
 * @property array<string, mixed> $mapping
 * @property bool $capture_sample
 * @property array<string, mixed>|null $sample_payload
 * @property CarbonImmutable|null $sample_captured_at
 * @property bool $is_active
 * @property CarbonImmutable|null $rotated_at
 */
#[Guarded(['id', 'ulid'])]
#[Hidden(['token_hash', 'sample_payload'])]
final class CognitoSource extends Model
{
    use Auditable;
    use HasPublicUlid;
    use SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = ['mapping' => '{}', 'capture_sample' => false, 'is_active' => true];

    /**
     * @return BelongsTo<RequestType, $this>
     */
    public function requestType(): BelongsTo
    {
        return $this->belongsTo(RequestType::class)->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mapping' => 'array',
            'capture_sample' => 'boolean',
            'sample_payload' => 'encrypted:array',
            'sample_captured_at' => 'immutable_datetime',
            'is_active' => 'boolean',
            'rotated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return list<string>
     */
    protected function auditExcept(): array
    {
        return ['token_hash', 'sample_payload'];
    }
}
