<?php

declare(strict_types=1);

namespace App\Domain\Access\Data;

use App\Domain\Access\Models\Role;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class RoleSummaryData extends Data
{
    /**
     * @param  array<string, string>  $label
     * @param  array<string, string>  $description
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $label,
        public array $description,
        public bool $isSystem,
        public bool $isSuperAdmin,
        public bool $apiOnly,
        public bool $requiresTwoFactor,
        public string $homeLayout,
        public int $usersCount,
        public int $permissionsCount,
    ) {}

    public static function fromRole(Role $role, int $usersCount, int $permissionsCount): self
    {
        /** @var array<string, string> $label */
        $label = $role->getTranslations('label');
        /** @var array<string, string> $description */
        $description = $role->getTranslations('description');

        return new self(
            id: $role->ulid,
            name: $role->name,
            label: $label,
            description: $description,
            isSystem: $role->is_system,
            isSuperAdmin: $role->is_super_admin,
            apiOnly: $role->api_only,
            requiresTwoFactor: $role->requires_2fa,
            homeLayout: $role->home_layout->value,
            usersCount: $usersCount,
            permissionsCount: $permissionsCount,
        );
    }
}
