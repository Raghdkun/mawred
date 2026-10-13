<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Enums\HomeLayout;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Support\AccessEpoch;

final readonly class UpdateRoleDetails
{
    public function __construct(private AccessEpoch $epoch) {}

    /**
     * @param  array{en: string, ar: string}  $label
     * @param  array{en?: string|null, ar?: string|null}  $description
     */
    public function handle(Role $role, array $label, array $description, bool $requiresTwoFactor, HomeLayout $homeLayout): Role
    {
        $role->setTranslations('label', $label);
        $role->setTranslations('description', array_filter($description, fn (?string $value): bool => $value !== null && $value !== ''));
        $role->forceFill([
            'requires_2fa' => $role->is_super_admin || $requiresTwoFactor,
            'home_layout' => $homeLayout,
        ])->save();

        $this->epoch->bump();

        return $role;
    }
}
