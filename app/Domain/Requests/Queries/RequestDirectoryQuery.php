<?php

declare(strict_types=1);

namespace App\Domain\Requests\Queries;

use App\Domain\Access\Queries\ScopedQuery;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Requests\Enums\RequestStatus;
use App\Domain\Requests\Models\EmployeeRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Requests HR can see (`requests.view`, scoped by the requester's placement, ADR-0005).
 *
 * @extends ScopedQuery<EmployeeRequest>
 */
final class RequestDirectoryQuery extends ScopedQuery
{
    private ?RequestStatus $status = null;

    private ?int $typeId = null;

    private string $search = '';

    public function withStatus(?RequestStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function ofType(?int $typeId): self
    {
        $this->typeId = $typeId;

        return $this;
    }

    public function search(string $search): self
    {
        $this->search = trim($search);

        return $this;
    }

    protected function permission(): string
    {
        return 'requests.view';
    }

    protected function subject(): SubjectBinding
    {
        return SubjectBinding::employee('requests.requester_employee_id');
    }

    /**
     * @return Builder<EmployeeRequest>
     */
    protected function baseQuery(): Builder
    {
        return EmployeeRequest::query()
            ->with(['type', 'requester'])
            ->when($this->status instanceof RequestStatus, fn (Builder $q) => $q->where('requests.status', $this->status?->value))
            ->when($this->typeId !== null, fn (Builder $q) => $q->where('requests.request_type_id', $this->typeId))
            ->when($this->search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('requests.number', 'ilike', '%'.addcslashes($this->search, '%_\\').'%')
                ->orWhereIn('requests.requester_employee_id', fn (QueryBuilder $sub) => $sub->select('id')->from('employees')->whereRaw('search_text ilike ?', ['%'.addcslashes(mb_strtolower($this->search), '%_\\').'%']))))
            ->latest('requests.submitted_at')
            ->orderByDesc('requests.id');
    }
}
