<?php

declare(strict_types=1);

namespace App\Domain\Audit\Queries;

use App\Domain\Access\Models\User;
use App\Domain\Access\Queries\ScopedQuery;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Audit\Models\Activity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Audit entries visible to a user (screen 43). With the Own scope a user sees what they did.
 *
 * @extends ScopedQuery<Activity>
 */
final class AuditLogQuery extends ScopedQuery
{
    /** @var array{log?: string|null, event?: string|null, actor?: string|null, from?: string|null, to?: string|null, search?: string|null} */
    private array $filters = [];

    /**
     * @param  array{log?: string|null, event?: string|null, actor?: string|null, from?: string|null, to?: string|null, search?: string|null}  $filters
     */
    public function filter(array $filters): self
    {
        $this->filters = $filters;

        return $this;
    }

    protected function permission(): string
    {
        return 'audit.view';
    }

    protected function subject(): SubjectBinding
    {
        return SubjectBinding::user('causer_id');
    }

    protected function baseQuery(): Builder
    {
        $filters = $this->filters;

        return Activity::query()
            ->with(['causer', 'subject', 'impersonator'])
            ->when($filters['log'] ?? null, fn (Builder $q, string $log) => $q->where('log_name', $log))
            ->when($filters['event'] ?? null, fn (Builder $q, string $event) => $q->where('event', $event))
            ->when($filters['actor'] ?? null, fn (Builder $q, string $ulid) => $q
                ->where('causer_type', (new User)->getMorphClass())
                ->whereIn('causer_id', User::query()->whereUlid($ulid)->select('id')))
            // Partition pruning (ADR-0018): date bounds go straight onto created_at.
            ->when($filters['from'] ?? null, fn (Builder $q, string $from) => $q->where('created_at', '>=', CarbonImmutable::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $q, string $to) => $q->where('created_at', '<', CarbonImmutable::parse($to)->addDay()->startOfDay()))
            ->when($filters['search'] ?? null, fn (Builder $q, string $term) => $q->where('description', 'ilike', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%'))->latest()
            ->orderByDesc('id');
    }
}
