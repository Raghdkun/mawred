<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $owner
 * @property string $key
 * @property string $request_hash
 * @property string $status
 * @property int|null $response_status
 * @property array<string, string|null>|null $response_headers
 * @property string|null $response_body
 * @property Carbon $expires_at
 */
#[Fillable(['owner', 'key', 'request_hash', 'status', 'response_status', 'response_headers', 'response_body', 'expires_at'])]
final class IdempotencyKey extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response_status' => 'integer',
            'response_headers' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
