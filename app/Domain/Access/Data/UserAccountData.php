<?php

declare(strict_types=1);

namespace App\Domain\Access\Data;

use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class UserAccountData extends Data
{
    /**
     * @param  list<array{name: string, label: array<string, string>}>  $roles
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public string $status,
        public bool $twoFactorEnabled,
        public ?string $lastLoginAt,
        public array $roles,
    ) {}

    public static function fromUser(User $user): self
    {
        $roles = [];
        foreach ($user->roles as $role) {
            if ($role instanceof Role) {
                /** @var array<string, string> $label */
                $label = $role->getTranslations('label');
                $roles[] = ['name' => $role->name, 'label' => $label];
            }
        }

        return new self(
            id: $user->ulid,
            name: $user->name,
            email: $user->email,
            status: $user->status,
            twoFactorEnabled: $user->two_factor_confirmed_at !== null,
            lastLoginAt: $user->last_login_at?->toIso8601String(),
            roles: $roles,
        );
    }
}
