<?php

declare(strict_types=1);

namespace App\Domain\Requests\Support;

use App\Domain\Access\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Who an approver can hand a task to: active colleagues with an employee record, by name. Not
 * scoped on purpose (any approver may delegate across the company); callers expose names only.
 */
final class DelegateCandidates
{
    /**
     * @return Builder<User>
     */
    public function for(User $viewer, string $search): Builder
    {
        $search = mb_strtolower(trim($search));

        return User::query()
            ->where('status', 'active')
            ->whereKeyNot($viewer->id)
            ->whereIn('id', fn (QueryBuilder $sub) => $sub->select('user_id')->from('employees')->whereNotNull('user_id')->whereNull('deleted_at')
                ->when($search !== '', fn (QueryBuilder $q) => $q->where('search_text', 'ilike', '%'.addcslashes($search, '%_\\').'%')))
            ->orderBy('name')
            ->orderBy('id');
    }
}
