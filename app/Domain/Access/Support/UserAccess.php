<?php

declare(strict_types=1);

namespace App\Domain\Access\Support;

use App\Domain\Access\Enums\FieldAccess;
use App\Domain\Access\Enums\HomeLayout;
use App\Domain\Access\Enums\Scope;

/**
 * Everything the access layer knows about one user, resolved once per request and cached
 * per access epoch (ADR-0005).
 */
final readonly class UserAccess
{
    /**
     * @param  array<string, list<Grant>>  $grants  permission => grants from each role
     * @param  array<string, FieldAccess>  $fields  field key => strongest access
     * @param  list<string>  $roles  role names
     */
    public function __construct(
        public int $userId,
        public bool $isSuperAdmin,
        public bool $apiOnly,
        public bool $requiresTwoFactor,
        public HomeLayout $homeLayout,
        public array $grants,
        public array $fields,
        public array $roles,
    ) {}

    public function can(string $permission): bool
    {
        return $this->isSuperAdmin || ($this->grants[$permission] ?? []) !== [];
    }

    /**
     * @return list<Grant>
     */
    public function grantsFor(string $permission): array
    {
        if ($this->isSuperAdmin) {
            return [new Grant(0, Scope::All)];
        }

        return $this->grants[$permission] ?? [];
    }

    /** The widest scope held for a permission, or null when not granted. */
    public function widestScope(string $permission): ?Scope
    {
        $widest = null;

        foreach ($this->grantsFor($permission) as $grant) {
            if (! $widest instanceof Scope || $grant->scope->isWiderThan($widest)) {
                $widest = $grant->scope;
            }
        }

        return $widest;
    }

    public function fieldAccess(string $fieldKey): ?FieldAccess
    {
        if ($this->isSuperAdmin) {
            return FieldAccess::Write;
        }

        return $this->fields[$fieldKey] ?? null;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        if ($this->isSuperAdmin) {
            return PermissionCatalog::all();
        }

        return array_keys(array_filter($this->grants, fn (array $grants): bool => $grants !== []));
    }
}
