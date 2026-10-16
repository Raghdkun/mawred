<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Queries;

use App\Domain\Access\Queries\ScopedQuery;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Hiring\Enums\ApplicationStatus;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Support\HiringScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Applications the viewer can see (`hiring.view`): the board of a requisition, or a candidate's
 * applications across jobs.
 *
 * @extends ScopedQuery<JobApplication>
 */
final class ApplicationsQuery extends ScopedQuery
{
    private ?int $requisitionId = null;

    private ?int $candidateId = null;

    /** @var list<ApplicationStatus> */
    private array $statuses = [];

    public function __construct(ScopeResolver $resolver, private readonly HiringScope $scope)
    {
        parent::__construct($resolver);
    }

    public function forRequisition(int $requisitionId): self
    {
        $this->requisitionId = $requisitionId;

        return $this;
    }

    public function forCandidate(int $candidateId): self
    {
        $this->candidateId = $candidateId;

        return $this;
    }

    /**
     * @param  list<ApplicationStatus>  $statuses
     */
    public function withStatuses(array $statuses): self
    {
        $this->statuses = $statuses;

        return $this;
    }

    protected function permission(): string
    {
        return 'hiring.view';
    }

    protected function subject(): SubjectBinding
    {
        return $this->scope->applications();
    }

    /**
     * @return Builder<JobApplication>
     */
    protected function baseQuery(): Builder
    {
        return JobApplication::query()
            ->when($this->requisitionId !== null, fn (Builder $q) => $q->where('job_applications.job_requisition_id', $this->requisitionId))
            ->when($this->candidateId !== null, fn (Builder $q) => $q->where('job_applications.candidate_id', $this->candidateId))
            ->when($this->statuses !== [], fn (Builder $q) => $q->whereIn('job_applications.status', array_map(fn (ApplicationStatus $s): string => $s->value, $this->statuses)))
            ->oldest('job_applications.stage_entered_at')
            ->orderBy('job_applications.id');
    }
}
