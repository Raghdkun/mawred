<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Organization;

use App\Domain\Access\Models\User;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Queries\PositionDirectoryQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Organization\PositionResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Position picker source, optionally narrowed to one department.
 */
final class PositionSearchController extends Controller
{
    public function __invoke(Request $request, PositionDirectoryQuery $query, #[CurrentUser] User $viewer): AnonymousResourceCollection
    {
        Gate::authorize('organization.view');

        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'active' => ['nullable', 'boolean'],
            'department' => ['nullable', 'string', 'exists:departments,ulid'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $department = $request->filled('department')
            ? Department::query()->where('ulid', $request->string('department')->toString())->first()
            : null;

        $page = $query->search($request->string('search')->toString())
            ->activeOnly($request->boolean('active', true))
            ->inDepartment($department)
            ->visibleTo($viewer)
            ->cursorPaginate($request->integer('per_page', 20))
            ->withQueryString();

        return PositionResource::collection($page);
    }
}
