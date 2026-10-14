<?php

declare(strict_types=1);

namespace App\Support\Search;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * One searchable type (employees, branches…). Providers live in their domain and must apply the
 * viewer's scope themselves, through a ScopedQuery (ADR-0013).
 */
interface SearchProvider
{
    public function type(): string;

    /** The ability a viewer needs before this type is searched at all. */
    public function ability(): string;

    /**
     * @return list<SearchHitData>
     */
    public function search(Authenticatable $viewer, string $term, int $limit): array;
}
