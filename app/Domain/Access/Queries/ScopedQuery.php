<?php

declare(strict_types=1);

namespace App\Domain\Access\Queries;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Access\Support\SubjectBinding;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Base for every list, search, report and export query (ADR-0005). Subclasses describe the base
 * query and how it reaches its subject; visibility is always applied by the ScopeResolver.
 *
 * @template TModel of Model
 */
abstract class ScopedQuery
{
    public function __construct(protected readonly ScopeResolver $resolver) {}

    /** The permission whose scope governs visibility, e.g. `audit.view`. */
    abstract protected function permission(): string;

    abstract protected function subject(): SubjectBinding;

    /**
     * @return Builder<TModel>
     */
    abstract protected function baseQuery(): Builder;

    /**
     * @return Builder<TModel>
     */
    public function visibleTo(User $user): Builder
    {
        return $this->resolver->apply($this->baseQuery(), $user, $this->permission(), $this->subject());
    }
}
