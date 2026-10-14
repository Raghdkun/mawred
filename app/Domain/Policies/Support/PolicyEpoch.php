<?php

declare(strict_types=1);

namespace App\Domain\Policies\Support;

use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Cache generation for resolved policies (ADR-0010). Any change to sets, values, assignments,
 * leave types or the branch fallbacks bumps it.
 */
final readonly class PolicyEpoch
{
    private const string KEY = 'mawred:policy:epoch';

    public function __construct(private Cache $cache) {}

    public function current(): int
    {
        $value = $this->cache->get(self::KEY, 1);

        return is_int($value) ? $value : 1;
    }

    public function bump(): void
    {
        if (! $this->cache->has(self::KEY)) {
            $this->cache->forever(self::KEY, 1);
        }

        $this->cache->increment(self::KEY);
    }
}
