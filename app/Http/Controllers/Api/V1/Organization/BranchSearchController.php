<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Organization;

use App\Domain\Access\Models\User;
use App\Domain\Organization\Queries\BranchDirectoryQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Organization\BranchResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Branch picker source (search-first, cursor paged).
 */
final class BranchSearchController extends Controller
{
    public function __invoke(Request $request, BranchDirectoryQuery $query, #[CurrentUser] User $viewer): AnonymousResourceCollection
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

        return BranchResource::collection($page);
    }
}
