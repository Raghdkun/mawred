<?php

declare(strict_types=1);

namespace App\Domain\People\Actions;

use App\Domain\Access\Actions\AssignRoles;
use App\Domain\Access\Models\User;
use App\Domain\People\Models\UserInvitation;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/**
 * Creates the employee's account from a pending invitation: verified email, the Employee role,
 * and the link to their record. The invitation is single-use.
 */
final readonly class AcceptInvitation
{
    public function __construct(private ConnectionInterface $db, private AssignRoles $roles) {}

    public function handle(UserInvitation $invitation, string $password, string $locale): User
    {
        return $this->db->transaction(function () use ($invitation, $password, $locale): User {
            $invitation = UserInvitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            $employee = $invitation->employee;

            if (! $invitation->isPending() || $employee === null || $employee->user_id !== null) {
                throw new DomainRuleViolation('people.invitation_invalid', __('This invitation is no longer valid.'), status: 410);
            }

            $user = User::query()->forceCreate([
                'name' => $employee->name_en,
                'email' => $invitation->email,
                'password' => $password,
                'status' => 'active',
                'locale' => $locale,
                'email_verified_at' => now(),
            ]);

            $this->roles->handle(Actor::system('invitation'), $user, ['employee' => []]);
            $employee->forceFill(['user_id' => $user->id])->save();
            $invitation->forceFill(['accepted_at' => now()])->save();

            return $user;
        });
    }
}
