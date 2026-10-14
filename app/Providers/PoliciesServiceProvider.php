<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Policies\Support\BranchHolidays;
use App\Domain\Policies\Support\PolicyEpoch;
use App\Domain\Policies\Support\PolicyKeys;
use App\Domain\Policies\Support\PolicyResolver;
use App\Domain\Policies\Support\PolicyTargets;
use Illuminate\Support\ServiceProvider;

/**
 * Policy engine wiring (ADR-0010): one key registry and resolver memo per request or job.
 */
final class PoliciesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PolicyEpoch::class);
        $this->app->scoped(PolicyKeys::class);
        $this->app->scoped(PolicyResolver::class);
        $this->app->scoped(BranchHolidays::class);
        $this->app->scoped(PolicyTargets::class);
    }
}
