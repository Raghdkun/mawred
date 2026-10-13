<?php

declare(strict_types=1);

namespace App\Domain\Access\Support;

use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Cache generation for resolved access. Any change to roles, grants, field permissions or
 * anchors bumps the epoch, so every cached UserAccess becomes stale at once.
 */
final readonly class AccessEpoch
{
    private const string KEY = 'mawred:access:epoch';

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
