<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\DefaultRoles;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

/**
 * Creates a staff account from the server's command line (`mawred:create-admin`, D136): an active
 * user with a verified email and the given role, but no usable password until they open a password
 * link (`IssuePasswordSetupLink`). Like the owner, an admin needs no employee record.
 */
final readonly class CreateAdminAccount
{
    public function __construct(private ConnectionInterface $db, private AssignRoles $roles) {}

    public function handle(Actor $actor, string $name, string $email, string $role): User
    {
        $name = trim($name);
        $email = Str::lower(trim($email));

        if ($name === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainRuleViolation('access.admin_invalid', __('Enter a valid email address.'));
        }

        if ($role === DefaultRoles::INTEGRATION_BOT) {
            throw new DomainRuleViolation('access.unknown_role', __('Unknown role :role.', ['role' => $role]));
        }

        if (User::withTrashed()->whereRaw('lower(email) = ?', [$email])->exists()) {
            throw new DomainRuleViolation('access.email_taken', __('That email is already in use.'));
        }

        return $this->db->transaction(function () use ($actor, $name, $email, $role): User {
            $user = User::query()->forceCreate([
                'name' => $name,
                'email' => $email,
                'password' => Str::random(64),
                'status' => 'active',
                'email_verified_at' => now(),
            ]);

            $this->roles->handle($actor, $user, [$role => []]);

            return $user;
        });
    }
}
