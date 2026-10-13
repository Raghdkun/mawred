<?php

declare(strict_types=1);

namespace App\Domain\Organization\Queries;

use App\Domain\Access\Queries\ScopedQuery;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Position;
use App\Support\Search\SearchText;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends ScopedQuery<Position>
 */
final class PositionDirectoryQuery extends ScopedQuery
{
    private ?string $search = null;

    private bool $activeOnly = false;

    private ?Department $department = null;

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

    public function inDepartment(?Department $department): self
    {
        $this->department = $department;

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
        return Position::query()
            ->with(['department:id,ulid', 'grade:id,ulid'])
            ->when($this->search, fn (Builder $query, string $term) => $query->where('search_text', 'like', SearchText::likePattern($term)))
            ->when($this->activeOnly, fn (Builder $query) => $query->where('is_active', true))
            ->when($this->department, fn (Builder $query, Department $department) => $query->where('department_id', $department->id))
            ->orderBy('code')
            ->orderBy('id');
    }
}
