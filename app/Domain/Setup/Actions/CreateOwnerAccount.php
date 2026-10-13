<?php

declare(strict_types=1);

namespace App\Domain\Setup\Actions;

use App\Domain\Access\Actions\SeedDefaultRoles;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\DefaultRoles;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Bootstraps a fresh install (D4): seeds roles and permissions and creates the first Super Admin
 * in the `invited` state. They set a password through the one-time setup link.
 */
final readonly class CreateOwnerAccount
{
    public function __construct(private SeedDefaultRoles $roles) {}

    public function handle(string $name, string $email): User
    {
        $this->roles->handle();

        $email = Str::lower(trim($email));

        if (User::role(DefaultRoles::SUPER_ADMIN)->exists()) {
            throw new DomainRuleViolation('setup.owner_exists', __('A Super Admin already exists.'));
        }

        if (User::query()->where('email', $email)->exists()) {
            throw new DomainRuleViolation('setup.email_taken', __('That email is already in use.'));
        }

        return DB::transaction(function () use ($name, $email): User {
            $owner = new User;
            $owner->forceFill([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
                'status' => 'invited',
            ])->save();

            $owner->assignRole(DefaultRoles::SUPER_ADMIN);

            return $owner;
        });
    }
}
