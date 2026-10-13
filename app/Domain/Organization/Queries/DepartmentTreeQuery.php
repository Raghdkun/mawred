<?php

declare(strict_types=1);

namespace App\Domain\Organization\Queries;

use App\Domain\Access\Queries\ScopedQuery;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Organization\Models\Department;
use App\Support\Search\SearchText;
use Illuminate\Database\Eloquent\Builder;

/**
 * Departments in tree order (ltree path order puts every parent before its children).
 *
 * @extends ScopedQuery<Department>
 */
final class DepartmentTreeQuery extends ScopedQuery
{
    private ?string $search = null;

    private bool $activeOnly = false;

    public function search(?string $term): self
    {
        $this->search = $term !== null && trim($term) !== '' ? $term : null;

        return $this;
    }

    public function activeOnly(bool $activeOnly = true): self
    {
        $this->activeOnly = $activeOnly;

        return $this;
    }

    protected function permission(): string
    {
        return 'organization.view';
    }

    protected function subject(): SubjectBinding
    {
        return SubjectBinding::reference();
    }

    protected function baseQuery(): Builder
    {
        return Department::query()
            ->with(['parent:id,ulid', 'costCenter:id,ulid'])
            ->when($this->search, fn (Builder $query, string $term) => $query->where('search_text', 'like', SearchText::likePattern($term)))
            ->when($this->activeOnly, fn (Builder $query) => $query->where('is_active', true))
            ->orderBy('path')
            ->orderBy('id');
    }
}
