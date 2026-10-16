<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Queries;

use App\Domain\Access\Queries\ScopedQuery;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Hiring\Enums\RequisitionStatus;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Support\HiringScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Requisitions the viewer can see (`hiring.view`, by branch, department, hiring manager or
 * hiring team, ADR-0005).
 *
 * @extends ScopedQuery<JobRequisition>
 */
final class RequisitionsQuery extends ScopedQuery
{
    /** @var list<RequisitionStatus> */
    private array $statuses = [];

    private ?int $branchId = null;

    private string $search = '';

    public function __construct(ScopeResolver $resolver, private readonly HiringScope $scope)
    {
        parent::__construct($resolver);
    }

    /**
     * @param  list<RequisitionStatus>  $statuses
     */
    public function withStatuses(array $statuses): self
    {
        $this->statuses = $statuses;

        return $this;
    }

    public function inBranch(?int $branchId): self
    {
        $this->branchId = $branchId;

        return $this;
    }

    public function search(string $search): self
    {
        $this->search = trim($search);

        return $this;
    }

    protected function permission(): string
    {
        return 'hiring.view';
    }

    protected function subject(): SubjectBinding
    {
        return $this->scope->requisitions();
    }

    /**
     * @return Builder<JobRequisition>
     */
    protected function baseQuery(): Builder
    {
        $like = '%'.addcslashes(mb_strtolower($this->search), '%_\\').'%';

        return JobRequisition::query()
            ->when($this->statuses !== [], fn (Builder $q) => $q->whereIn('job_requisitions.status', array_map(fn (RequisitionStatus $s): string => $s->value, $this->statuses)))
            ->when($this->branchId !== null, fn (Builder $q) => $q->where('job_requisitions.branch_id', $this->branchId))
            ->when($this->search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->whereRaw('lower(job_requisitions.number) like ?', [$like])
                ->orWhereRaw("lower(job_requisitions.title->>'en') like ?", [$like])
                ->orWhereRaw("job_requisitions.title->>'ar' like ?", ['%'.addcslashes($this->search, '%_\\').'%'])))
            ->latest('job_requisitions.created_at')
            ->orderByDesc('job_requisitions.id');
    }
}
