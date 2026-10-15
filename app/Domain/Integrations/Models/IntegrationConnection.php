<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Domain\Integrations\Enums\IntegrationStatus;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * A connection to an outside service (ADR-0015). Credentials are encrypted at rest, never
 * serialised and never written to the audit log (only that they changed).
 *
 * @property int $id
 * @property string $ulid
 * @property string $type
 * @property string $name
 * @property array<string, mixed> $config
 * @property array<string, string>|null $credentials
 * @property string $status
 * @property CarbonImmutable|null $last_success_at
 * @property CarbonImmutable|null $last_failure_at
 * @property CarbonImmutable|null $health_checked_at
 * @property string|null $last_error
 */
#[Guarded(['id', 'ulid'])]
#[Hidden(['credentials'])]
final class IntegrationConnection extends Model
{
    use Auditable;
    use HasPublicUlid;

    /** @var array<string, mixed> */
    protected $attributes = ['config' => '{}', 'status' => 'not_configured'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'credentials' => 'encrypted:array',
            'last_success_at' => 'immutable_datetime',
            'last_failure_at' => 'immutable_datetime',
            'health_checked_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return list<string>
     */
    protected function auditExcept(): array
    {
        return ['credentials', 'last_success_at', 'last_failure_at', 'last_error', 'health_checked_at', 'status'];
    }

    public function healthStatus(): IntegrationStatus
    {
        return IntegrationStatus::tryFrom($this->status) ?? IntegrationStatus::Unverified;
    }

    /**
     * A credential for display: the last four characters only.
     */
    public function masked(string $key): ?string
    {
        $value = $this->credentials[$key] ?? null;

        return is_string($value) && $value !== '' ? '••••'.mb_substr($value, -4) : null;
    }
}
