<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Access;

use App\Domain\Access\Models\User;
use App\Domain\Access\Queries\UserDirectoryQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserAccountResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Async user picker source (brief §3: pickers are search-first and server-paged).
 */
final class UserSearchController extends Controller
{
    public function __invoke(Request $request, UserDirectoryQuery $query, #[CurrentUser] User $viewer): AnonymousResourceCollection
    {
        Gate::authorize('access.view');

        $request->validate(['search' => ['nullable', 'string', 'max:100'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        $page = $query->search($request->string('search')->toString())
            ->visibleTo($viewer)
            ->cursorPaginate($request->integer('per_page', 20))
            ->withQueryString();

        return UserAccountResource::collection($page);
    }
}
