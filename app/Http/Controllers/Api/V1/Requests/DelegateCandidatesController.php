<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Requests;

use App\Domain\Access\Models\User;
use App\Domain\Requests\Support\DelegateCandidates;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DelegateCandidateResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Delegate picker source (handoff 13): colleagues by name only, no email or placement.
 */
final class DelegateCandidatesController extends Controller
{
    public function __invoke(Request $request, DelegateCandidates $candidates, #[CurrentUser] User $viewer): AnonymousResourceCollection
    {
        $request->validate(['search' => ['nullable', 'string', 'max:100'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:50']]);

        $page = $candidates->for($viewer, $request->string('search')->toString())
            ->cursorPaginate($request->integer('per_page', 20))
            ->withQueryString();

        return DelegateCandidateResource::collection($page);
    }
}
