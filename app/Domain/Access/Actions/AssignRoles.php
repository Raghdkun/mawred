<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Enums\AnchorType;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RoleAssignmentAnchor;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\AccessEpoch;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Support\Facades\DB;

/**
 * Sets a user's roles and, per role, the branches/departments its branch- and department-scoped
 * grants are anchored to (D5). No anchors means "the user's own placement".
 */
final readonly class AssignRoles
{
    public function __construct(private AccessEpoch $epoch) {}

    /**
     * @param  array<string, array{branches?: list<int>, departments?: list<int>}>  $assignments  role name => anchors
     */
    public function handle(Actor $actor, User $user, array $assignments): void
    {
        $roles = Role::query()->whereIn('name', array_keys($assignments))->get()->keyBy('name');

        foreach (array_keys($assignments) as $name) {
            if (! $roles->has($name)) {
                throw new DomainRuleViolation('access.unknown_role', __('Unknown role :role.', ['role' => $name]));
            }
        }

        $grantsSuperAdmin = $roles->contains(fn (Role $role): bool => $role->is_super_admin);
        $actorIsSuperAdmin = $actor->user?->roles()->where('is_super_admin', true)->exists() ?? false;

        if ($grantsSuperAdmin && ! $actorIsSuperAdmin && $actor->isHuman()) {
            throw new DomainRuleViolation('access.super_admin_assignment', __('Only a Super Admin can grant the Super Admin role.'), status: 403);
        }

        DB::transaction(function () use ($user, $assignments, $roles): void {
            $user->syncRoles($roles->values()->all());

            RoleAssignmentAnchor::query()->where('user_id', $user->id)->delete();

            foreach ($assignments as $name => $anchors) {
                $role = $roles->get($name);

                if (! $role instanceof Role) {
                    continue;
                }

                foreach (['branches' => AnchorType::Branch, 'departments' => AnchorType::Department] as $key => $type) {
                    foreach (array_unique($anchors[$key] ?? []) as $anchorId) {
                        RoleAssignmentAnchor::query()->create([
                            'user_id' => $user->id,
                            'role_id' => $role->id,
                            'anchor_type' => $type,
                            'anchor_id' => $anchorId,
                        ]);
                    }
                }
            }

            activity('access')
                ->performedOn($user)
                ->event('roles.assigned')
                ->withProperties(['assignments' => $assignments])
                ->log('User roles updated');
        });

        $this->epoch->bump();
    }
}
