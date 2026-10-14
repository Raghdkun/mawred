<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Organization;

use App\Domain\Access\Models\User;
use App\Domain\Organization\Queries\DepartmentTreeQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Organization\DepartmentResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Department picker source, in tree order.
 */
final class DepartmentSearchController extends Controller
{
    public function __invoke(Request $request, DepartmentTreeQuery $query, #[CurrentUser] User $viewer): AnonymousResourceCollection
    {
        Gate::authorize('organization.view');

        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = $query->search($request->string('search')->toString())
            ->activeOnly($request->boolean('active', true))
            ->visibleTo($viewer)
            ->cursorPaginate($request->integer('per_page', 20))
            ->withQueryString();

        return DepartmentResource::collection($page);
    }
}
