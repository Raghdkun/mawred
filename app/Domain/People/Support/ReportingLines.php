<?php

declare(strict_types=1);

namespace App\Domain\People\Support;

use App\Domain\People\Models\Employee;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/**
 * Maintains `employee_reporting_paths`, the closure table behind the `team_tree` scope
 * (ADR-0005): one row per (ancestor, descendant) pair, including each employee with itself at
 * depth 0. Moving someone re-links their whole subtree under the new manager.
 */
final readonly class ReportingLines
{
    public const int LOCK = 4_201_002;

    public function __construct(private ConnectionInterface $db) {}

    public function ensureSelf(Employee $employee): void
    {
        $this->db->insert(
            'insert into employee_reporting_paths (ancestor_id, descendant_id, depth) values (?, ?, 0) on conflict do nothing',
            [$employee->id, $employee->id],
        );
    }

    public function wouldCycle(Employee $employee, ?int $managerId): bool
    {
        if ($managerId === null) {
            return false;
        }

        if ($managerId === $employee->id) {
            return true;
        }

        return $this->db->table('employee_reporting_paths')
            ->where('ancestor_id', $employee->id)
            ->where('descendant_id', $managerId)
            ->exists();
    }

    public function move(Employee $employee, ?int $managerId): void
    {
        $this->db->transaction(function () use ($employee, $managerId): void {
            $this->db->select('select pg_advisory_xact_lock(?)', [self::LOCK]);
            $this->ensureSelf($employee);

            if ($this->wouldCycle($employee, $managerId)) {
                throw new DomainRuleViolation(
                    'people.reporting_cycle',
                    __('Someone cannot report to themselves or to a person in their own team.'),
                    ['managerId' => [__('Someone cannot report to themselves or to a person in their own team.')]],
                );
            }

            // Detach the subtree from every old ancestor above it.
            $this->db->delete(
                'delete from employee_reporting_paths
                 where descendant_id in (select descendant_id from employee_reporting_paths where ancestor_id = ?)
                   and ancestor_id in (select ancestor_id from employee_reporting_paths where descendant_id = ? and ancestor_id <> ?)',
                [$employee->id, $employee->id, $employee->id],
            );

            if ($managerId !== null) {
                $this->db->insert(
                    'insert into employee_reporting_paths (ancestor_id, descendant_id, depth)
                     select above.ancestor_id, below.descendant_id, above.depth + below.depth + 1
                     from employee_reporting_paths above
                     cross join employee_reporting_paths below
                     where above.descendant_id = ? and below.ancestor_id = ?',
                    [$managerId, $employee->id],
                );
            }
        });
    }
}
