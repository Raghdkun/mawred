<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Search;

use App\Domain\Access\Models\User;
use App\Http\Controllers\Controller;
use App\Support\Search\GlobalSearch;
use App\Support\Search\SearchHitData;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Global search for the ⌘K palette and the mobile app (ADR-0013). Results are grouped by type
 * and limited per type; every provider applies the caller's scope.
 */
final class GlobalSearchController extends Controller
{
    public function __invoke(Request $request, GlobalSearch $search, #[CurrentUser] User $viewer): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'types' => ['nullable', 'array'],
            'types.*' => ['string', Rule::in($search->types())],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        /** @var list<string>|null $types */
        $types = $request->filled('types') ? array_values($request->array('types')) : null;
        $results = $search->search($viewer, $request->string('q')->toString(), $types, $request->integer('limit', 5));

        return response()->json([
            'data' => array_map(
                fn (string $type, array $hits): array => ['type' => $type, 'items' => array_map(fn (SearchHitData $hit): array => $hit->toArray(), $hits)],
                array_keys($results),
                array_values($results),
            ),
        ]);
    }
}
