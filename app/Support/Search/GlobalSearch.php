<?php

declare(strict_types=1);

namespace App\Support\Search;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Unions the per-type providers (ADR-0013). Types the viewer has no ability for are skipped
 * entirely, so nothing out of scope is ever queried.
 */
final class GlobalSearch
{
    /** @var array<string, SearchProvider> */
    private array $providers = [];

    public function __construct(private readonly Gate $gate) {}

    public function register(SearchProvider $provider): void
    {
        $this->providers[$provider->type()] = $provider;
    }

    /**
     * @param  list<string>|null  $types
     * @return array<string, list<SearchHitData>> type => hits (types with no hits are left out)
     */
    public function search(Authenticatable $viewer, string $term, ?array $types = null, int $limit = 5): array
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $results = [];

        foreach ($this->providers as $type => $provider) {
            if (($types !== null && ! in_array($type, $types, true)) || ! $this->gate->forUser($viewer)->allows($provider->ability())) {
                continue;
            }

            $hits = $provider->search($viewer, $term, $limit);

            if ($hits !== []) {
                $results[$type] = $hits;
            }
        }

        return $results;
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys($this->providers);
    }
}
