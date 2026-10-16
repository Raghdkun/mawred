<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Support;

use App\Domain\Access\Enums\Scope;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\Grant;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\People\Support\EmployeeScopePredicates;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Who sees which hiring records (ADR-0005, D82). A requisition belongs to a place, so org scopes
 * match its branch, department or hiring manager. "Own" means the requisitions you work on: as a
 * member of the hiring team, as its hiring manager or as the person who asked for it.
 * Applications and candidates follow the requisitions they are in.
 */
final readonly class HiringScope
{
    public function __construct(private ConnectionInterface $db, private EmployeeScopePredicates $people) {}

    public function requisitions(): SubjectBinding
    {
        return SubjectBinding::custom(function (Builder $query, User $user, Grant $grant): void {
            $query->whereIn($query->qualifyColumn('id'), $this->requisitionIds($user, $grant));
        });
    }

    public function applications(): SubjectBinding
    {
        return SubjectBinding::custom(function (Builder $query, User $user, Grant $grant): void {
            $query->whereIn($query->qualifyColumn('job_requisition_id'), $this->requisitionIds($user, $grant));
        });
    }

    public function candidates(): SubjectBinding
    {
        return SubjectBinding::custom(function (Builder $query, User $user, Grant $grant): void {
            $query->whereIn(
                $query->qualifyColumn('id'),
                $this->db->table('job_applications')->select('candidate_id')->whereIn('job_requisition_id', $this->requisitionIds($user, $grant)),
            );
        });
    }

    /**
     * Requisitions a grant narrower than "all" covers.
     */
    public function requisitionIds(User $user, Grant $grant): QueryBuilder
    {
        $own = $this->people->ownEmployee($user);

        return $this->db->table('job_requisitions')->select('id')->where(function (QueryBuilder $q) use ($user, $grant, $own): void {
            $q->whereIn('id', $this->db->table('requisition_members')->select('job_requisition_id')->where('user_id', $user->id))
                ->orWhere('requested_by_user_id', $user->id)
                ->orWhereIn('hiring_manager_employee_id', $own);

            match ($grant->scope) {
                Scope::DirectReports, Scope::TeamTree => $q->orWhereIn('hiring_manager_employee_id', $this->people->visibleEmployeeIds($user, $grant)),
                Scope::Department => $q->orWhereIn('department_id', $this->people->departmentIds($user, $grant)),
                Scope::Branch => $q->orWhereIn('branch_id', $this->people->branchIds($user, $grant)),
                Scope::All => $q->orWhereRaw('1 = 1'),
                Scope::Own => null,
            };
        });
    }
}
