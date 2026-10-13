<?php

declare(strict_types=1);

namespace App\Domain\Access\Support;

use App\Domain\Access\Contracts\OrgScopePredicates;
use App\Domain\Access\Enums\AnchorType;
use App\Domain\Access\Enums\FieldAccess;
use App\Domain\Access\Enums\HomeLayout;
use App\Domain\Access\Enums\Scope;
use App\Domain\Access\Models\User;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\JoinClause;

/**
 * The single place that turns (user, permission) into a yes/no or a SQL constraint (ADR-0005).
 * Policies, ScopedQuery, global search, reports, exports and channel auth all go through here,
 * so lists can never show what a policy would refuse.
 */
final class ScopeResolver
{
    /** @var array<int, UserAccess> */
    private array $memo = [];

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly Cache $cache,
        private readonly AccessEpoch $epoch,
        private readonly OrgScopePredicates $org,
    ) {}

    public function for(User $user): UserAccess
    {
        $key = sprintf('mawred:access:user:%d:%d', $user->id, $this->epoch->current());

        return $this->memo[$user->id] ??= $this->cache->remember($key, now()->addHour(), fn (): UserAccess => $this->load($user));
    }

    public function can(User $user, string $permission): bool
    {
        return $this->for($user)->can($permission);
    }

    public function fieldAccess(User $user, string $fieldKey): ?FieldAccess
    {
        return $this->for($user)->fieldAccess($fieldKey);
    }

    /**
     * Constrain a query to the records `$user` may see through `$permission`.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function apply(Builder $query, User $user, string $permission, SubjectBinding $subject): Builder
    {
        $grants = $this->for($user)->grantsFor($permission);

        if ($grants === []) {
            return $query->whereRaw('1 = 0');
        }

        foreach ($grants as $grant) {
            if ($grant->isUnrestricted()) {
                return $query;
            }
        }

        return $query->where(function (Builder $scoped) use ($grants, $user, $subject): void {
            foreach ($grants as $grant) {
                $scoped->orWhere(fn (Builder $branch) => $this->constrain($branch, $user, $grant, $subject));
            }
        });
    }

    /**
     * Whether `$user` may act on one record through `$permission`.
     */
    public function allows(User $user, string $permission, Model $record, SubjectBinding $subject): bool
    {
        $grants = $this->for($user)->grantsFor($permission);

        if ($grants === []) {
            return false;
        }

        foreach ($grants as $grant) {
            if ($grant->isUnrestricted()) {
                return true;
            }
        }

        $query = $record->newQuery()->whereKey($record->getKey());

        return $this->apply($query, $user, $permission, $subject)->exists();
    }

    public function forget(User $user): void
    {
        unset($this->memo[$user->id]);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    private function constrain(Builder $query, User $user, Grant $grant, SubjectBinding $subject): void
    {
        if ($grant->scope === Scope::Own) {
            if ($subject->userColumn !== null) {
                $query->where($subject->userColumn, $user->id);

                return;
            }

            /** @var string $employeeColumn */
            $employeeColumn = $subject->employeeColumn;
            $this->org->constrainOwnEmployee($query, $employeeColumn, $user);

            return;
        }

        if ($subject->employeeColumn !== null) {
            $this->org->constrainEmployees($query, $subject->employeeColumn, $user, $grant);

            return;
        }

        /** @var string $userColumn */
        $userColumn = $subject->userColumn;
        $this->org->constrainUsers($query, $userColumn, $user, $grant);
    }

    private function load(User $user): UserAccess
    {
        $roles = $this->db->table('model_has_roles as mhr')
            ->join('roles as r', 'r.id', '=', 'mhr.role_id')
            ->where('mhr.model_type', $user->getMorphClass())
            ->where('mhr.model_id', $user->id)
            ->get(['r.id', 'r.name', 'r.is_super_admin', 'r.api_only', 'r.requires_2fa', 'r.home_layout', 'r.rank'])
            ->map(fn (object $row): array => [
                'id' => $this->int($row->id),
                'name' => $this->str($row->name),
                'is_super_admin' => (bool) $row->is_super_admin,
                'api_only' => (bool) $row->api_only,
                'requires_2fa' => (bool) $row->requires_2fa,
                'home_layout' => $this->str($row->home_layout),
                'rank' => $this->int($row->rank),
            ]);

        $roleIds = $roles->pluck('id')->all();

        /** @var array<int, array<string, list<int>>> $anchors */
        $anchors = [];
        foreach ($this->db->table('role_assignment_anchors')->where('user_id', $user->id)->get(['role_id', 'anchor_type', 'anchor_id']) as $anchor) {
            $anchors[$this->int($anchor->role_id)][$this->str($anchor->anchor_type)][] = $this->int($anchor->anchor_id);
        }

        $grants = [];
        $rows = $this->db->table('role_has_permissions as rhp')
            ->join('permissions as p', 'p.id', '=', 'rhp.permission_id')
            ->leftJoin('role_permission_scopes as rps', function (JoinClause $join): void {
                $join->on('rps.role_id', '=', 'rhp.role_id')->on('rps.permission_id', '=', 'rhp.permission_id');
            })
            ->whereIn('rhp.role_id', $roleIds)
            ->get(['rhp.role_id', 'p.name', 'rps.scope']);

        foreach ($rows as $row) {
            $roleId = $this->int($row->role_id);
            $scope = Scope::tryFrom($this->str($row->scope)) ?? Scope::Own;
            $roleAnchors = $anchors[$roleId] ?? [];

            $grants[$this->str($row->name)][] = new Grant(
                roleId: $roleId,
                scope: $scope,
                branchIds: $scope->acceptsAnchors() ? ($roleAnchors[AnchorType::Branch->value] ?? []) : [],
                departmentIds: $scope->acceptsAnchors() ? ($roleAnchors[AnchorType::Department->value] ?? []) : [],
            );
        }

        $fields = [];
        foreach ($this->db->table('field_permissions')->whereIn('role_id', $roleIds)->get(['field_key', 'access']) as $field) {
            $access = FieldAccess::tryFrom($this->str($field->access));
            $key = $this->str($field->field_key);

            if ($access instanceof FieldAccess && (! isset($fields[$key]) || $access === FieldAccess::Write)) {
                $fields[$key] = $access;
            }
        }

        $top = $roles->sortByDesc('rank')->first();

        return new UserAccess(
            userId: $user->id,
            isSuperAdmin: $roles->contains('is_super_admin', true),
            apiOnly: $roles->isNotEmpty() && $roles->every('api_only', true),
            requiresTwoFactor: $roles->contains('requires_2fa', true),
            homeLayout: HomeLayout::tryFrom($top['home_layout'] ?? '') ?? HomeLayout::Employee,
            grants: $grants,
            fields: $fields,
            roles: array_values($roles->map(fn (array $role): string => $role['name'])->all()),
        );
    }

    private function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function str(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
