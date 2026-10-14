<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Access;

use App\Domain\Access\Actions\AssignRoles;
use App\Domain\Access\Data\RoleSummaryData;
use App\Domain\Access\Data\UserAccountData;
use App\Domain\Access\Enums\Scope;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use App\Domain\Access\Queries\UserDirectoryQuery;
use App\Domain\Access\Support\PermissionCatalog;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Access\Support\SensitiveFieldRegistry;
use App\Domain\Audit\Queries\AuditLogQuery;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class UsersController extends Controller
{
    public function index(Request $request, UserDirectoryQuery $query, #[CurrentUser] User $viewer): Response
    {
        Gate::authorize('access.view');

        $search = $request->string('search')->toString();
        $page = $query->search($search)->visibleTo($viewer)->cursorPaginate(25)->withQueryString();

        return Inertia::render('access/users/index', [
            'users' => [
                'data' => array_map(UserAccountData::fromUser(...), $page->items()),
                'nextCursor' => $page->nextCursor()?->encode(),
                'prevCursor' => $page->previousCursor()?->encode(),
            ],
            'roles' => Role::query()->orderByDesc('rank')->get()->map(fn (Role $role): RoleSummaryData => RoleSummaryData::fromRole($role, 0, 0)),
            'filters' => ['search' => $search],
            'can' => [
                'assign' => Gate::allows('access.update'),
                'impersonate' => Gate::allows('access.configure') && (resolve(ScopeResolver::class)->for($viewer)->isSuperAdmin),
            ],
        ]);
    }

    public function updateRoles(Request $request, User $user, AssignRoles $assign, #[CurrentUser] User $actor): RedirectResponse
    {
        Gate::authorize('access.update');

        $request->validate([
            'roles' => ['present', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $roles = array_values(array_filter($request->array('roles'), is_string(...)));
        $assign->handle(Actor::user($actor), $user, array_fill_keys($roles, []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Roles updated for :name.', ['name' => $user->name])]);

        return back();
    }

    /**
     * "View as user": a read-only preview of what someone can do and see (screen 38). It runs
     * the ScopeResolver as that user; it never switches sessions.
     */
    public function preview(User $user, ScopeResolver $resolver, AuditLogQuery $audit, SensitiveFieldRegistry $sensitiveFields): Response
    {
        Gate::authorize('access.view');

        $access = $resolver->for($user);
        $matrix = [];

        foreach (PermissionCatalog::MODULES as $module => $definition) {
            foreach ($definition['actions'] as $action) {
                $scope = $access->widestScope("{$module}.{$action}");

                if ($scope instanceof Scope) {
                    $matrix[$module][$action] = $definition['scoped'] ? $scope->value : 'all';
                }
            }
        }

        $fields = [];
        foreach ($sensitiveFields->keys() as $field) {
            $fields[$field] = $access->fieldAccess($field)?->value;
        }

        return Inertia::render('access/users/preview', [
            'user' => UserAccountData::fromUser($user->load('roles')),
            'isSuperAdmin' => $access->isSuperAdmin,
            'requiresTwoFactor' => $access->requiresTwoFactor,
            'homeLayout' => $access->homeLayout->value,
            'matrix' => (object) $matrix,
            'fields' => $fields,
            'fieldLabels' => (object) $sensitiveFields->labels(),
            'samples' => [
                'auditEntries' => $access->can('audit.view') ? $audit->visibleTo($user)->count() : null,
            ],
        ]);
    }
}
