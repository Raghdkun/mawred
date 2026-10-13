<?php

declare(strict_types=1);

namespace App\Domain\Access\Actions;

use App\Domain\Access\Models\Role;
use App\Domain\Access\Support\AccessEpoch;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Support\Facades\DB;

final readonly class DeleteRole
{
    public function __construct(private AccessEpoch $epoch) {}

    public function handle(Role $role): void
    {
        if ($role->is_system) {
            throw new DomainRuleViolation('access.system_role', __('Seeded roles cannot be deleted. Remove their permissions instead.'));
        }

        $assigned = DB::table('model_has_roles')->where('role_id', $role->id)->count();

        if ($assigned > 0) {
            throw new DomainRuleViolation('access.role_in_use', __('This role is assigned to :count user(s). Reassign them first.', ['count' => $assigned]));
        }

        $role->delete();
        $this->epoch->bump();
    }
}
