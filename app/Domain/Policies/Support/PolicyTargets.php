<?php

declare(strict_types=1);

namespace App\Domain\Policies\Support;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\EmploymentType;
use App\Domain\Organization\Models\Grade;
use App\Domain\Organization\Models\Position;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Enums\PolicyLevel;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps assignment targets between internal ids, public ULIDs and display names. Names are memoised
 * per request, so explainers over many employees stay cheap.
 */
final class PolicyTargets
{
    /** @var array<string, string|null> */
    private array $names = [];

    /** @var array<string, string|null> */
    private array $ulids = [];

    /**
     * @return class-string<Model>|null
     */
    public static function modelFor(PolicyLevel $level): ?string
    {
        return match ($level) {
            PolicyLevel::Branch => Branch::class,
            PolicyLevel::Department => Department::class,
            PolicyLevel::Position => Position::class,
            PolicyLevel::Grade => Grade::class,
            PolicyLevel::EmploymentType => EmploymentType::class,
            PolicyLevel::Employee => Employee::class,
            PolicyLevel::Global => null,
        };
    }

    /** The internal id for a public ULID, or null when it doesn't exist (or is archived). */
    public function idFor(PolicyLevel $level, ?string $ulid): ?int
    {
        $model = self::modelFor($level);

        if ($model === null || $ulid === null) {
            return null;
        }

        $id = $model::query()->where('ulid', $ulid)->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    public function ulidFor(PolicyLevel $level, ?int $id): ?string
    {
        $model = self::modelFor($level);

        if ($model === null || $id === null) {
            return null;
        }

        $cacheKey = $level->value.':'.$id;

        if (! array_key_exists($cacheKey, $this->ulids)) {
            $ulid = $model::query()->withoutGlobalScopes()->whereKey($id)->value('ulid');
            $this->ulids[$cacheKey] = is_string($ulid) ? $ulid : null;
        }

        return $this->ulids[$cacheKey];
    }

    public function name(PolicyLevel $level, ?int $id): ?string
    {
        if ($id === null || $level === PolicyLevel::Global) {
            return null;
        }

        $locale = app()->getLocale();
        $cacheKey = $level->value.':'.$id.':'.$locale;

        if (array_key_exists($cacheKey, $this->names)) {
            return $this->names[$cacheKey];
        }

        $name = match ($level) {
            PolicyLevel::Branch => Branch::withTrashed()->find($id)?->getTranslation('name', $locale),
            PolicyLevel::Department => Department::withTrashed()->find($id)?->getTranslation('name', $locale),
            PolicyLevel::Position => Position::withTrashed()->find($id)?->getTranslation('title', $locale),
            PolicyLevel::Grade => Grade::withTrashed()->find($id)?->getTranslation('name', $locale),
            PolicyLevel::EmploymentType => EmploymentType::withTrashed()->find($id)?->getTranslation('name', $locale),
            PolicyLevel::Employee => Employee::withTrashed()->find($id)?->displayName($locale),
        };

        return $this->names[$cacheKey] = is_string($name) ? $name : null;
    }
}
