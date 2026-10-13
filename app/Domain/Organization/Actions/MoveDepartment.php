<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Department;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/**
 * Reparents a department (tree drag on screen 39) and rewrites the ltree path of its whole
 * subtree in one statement. Tree mutations are serialised with an advisory lock so two
 * concurrent moves cannot build a cycle.
 */
final readonly class MoveDepartment
{
    public const int TREE_LOCK = 4_201_001;

    public function __construct(private ConnectionInterface $db) {}

    public function handle(Department $department, ?Department $parent): Department
    {
        return $this->db->transaction(function () use ($department, $parent): Department {
            $this->db->select('select pg_advisory_xact_lock(?)', [self::TREE_LOCK]);

            $department->refresh();
            $parent?->refresh();

            if ($parent instanceof Department && ($parent->is($department) || $this->isWithin($parent, $department))) {
                throw new DomainRuleViolation(
                    'organization.department_cycle',
                    __('A department cannot move under itself or one of its sub-departments.'),
                    ['parentId' => [__('A department cannot move under itself or one of its sub-departments.')]],
                );
            }

            $oldPath = $department->path;
            $start = substr_count($oldPath, '.');
            $suffix = 'subpath(path, ?)';

            if (! $parent instanceof Department) {
                $expression = $suffix;
                $bindings = [$start];
            } else {
                $expression = "(?::ltree || {$suffix})";
                $bindings = [$parent->path, $start];
            }

            $this->db->update(
                "update departments set path = {$expression}, depth = nlevel({$expression}) - 1 where path <@ ?::ltree",
                [...$bindings, ...$bindings, $oldPath],
            );

            $department->parent_id = $parent?->id;
            $department->save();

            return $department->refresh();
        });
    }

    private function isWithin(Department $candidate, Department $root): bool
    {
        return $this->db->scalar('select ?::ltree <@ ?::ltree', [$candidate->path, $root->path]) === true;
    }
}
