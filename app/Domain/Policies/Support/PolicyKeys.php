<?php

declare(strict_types=1);

namespace App\Domain\Policies\Support;

use App\Domain\Policies\Keys\GeofenceModeKey;
use App\Domain\Policies\Keys\GraceKey;
use App\Domain\Policies\Keys\HourlyPermissionKey;
use App\Domain\Policies\Keys\LeaveRuleKey;
use App\Domain\Policies\Keys\OvertimeKey;
use App\Domain\Policies\Keys\PolicyKey;
use App\Domain\Policies\Keys\ProbationKey;
use App\Domain\Policies\Keys\RestKey;
use App\Domain\Policies\Keys\WeekendKey;
use App\Domain\Policies\Keys\WorkHoursKey;
use App\Domain\Policies\Models\LeaveType;

/**
 * The key registry (ADR-0010): fixed keys plus one `leave.type.<CODE>` key per active leave type.
 * A key that is not registered cannot be stored.
 */
final class PolicyKeys
{
    /** @var array<string, PolicyKey>|null */
    private ?array $keys = null;

    /**
     * @return array<string, PolicyKey> id => key, in editor order
     */
    public function all(): array
    {
        if ($this->keys !== null) {
            return $this->keys;
        }

        $keys = [];

        foreach (LeaveType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get() as $type) {
            $key = new LeaveRuleKey($type);
            $keys[$key->id()] = $key;
        }

        foreach ([new HourlyPermissionKey, new WeekendKey, new WorkHoursKey, new OvertimeKey, new GraceKey, new GeofenceModeKey, new RestKey, new ProbationKey] as $key) {
            $keys[$key->id()] = $key;
        }

        return $this->keys = $keys;
    }

    public function find(string $id): ?PolicyKey
    {
        return $this->all()[$id] ?? null;
    }

    public function forget(): void
    {
        $this->keys = null;
    }
}
