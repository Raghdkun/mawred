<?php

declare(strict_types=1);

namespace App\Domain\Access\Support;

use App\Domain\Access\Enums\Scope;

/**
 * One role's grant of one permission to a user: the scope plus any anchors that narrow
 * branch/department scopes to specific units (D5).
 */
final readonly class Grant
{
    /**
     * @param  list<int>  $branchIds
     * @param  list<int>  $departmentIds
     */
    public function __construct(
        public int $roleId,
        public Scope $scope,
        public array $branchIds = [],
        public array $departmentIds = [],
    ) {}

    public function isUnrestricted(): bool
    {
        return $this->scope === Scope::All;
    }
}
