<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Data\DepartmentInputData;
use App\Domain\Organization\Models\CostCenter;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Support\ReferenceValues;
use Illuminate\Database\ConnectionInterface;

/**
 * Creates or edits a department. A new department gets its ltree path (ancestor ids) right after
 * insert; a changed parent is handed to MoveDepartment so the subtree follows.
 */
final readonly class SaveDepartment
{
    public function __construct(private ConnectionInterface $db, private MoveDepartment $move) {}

    public function handle(DepartmentInputData $input, ?Department $department = null): Department
    {
        $department ??= new Department;
        $code = ReferenceValues::code($input->code);

        ReferenceValues::assertCodeAvailable(Department::class, $code, $department);

        $parent = $input->parentId === null ? null : Department::query()->where('ulid', $input->parentId)->firstOrFail();

        return $this->db->transaction(function () use ($department, $input, $code, $parent): Department {
            $department->setTranslations('name', ReferenceValues::translations($input->name));
            $department->fill([
                'code' => $code,
                'cost_center_id' => $input->costCenterId === null ? null : CostCenter::query()->where('ulid', $input->costCenterId)->value('id'),
                'is_active' => $input->isActive,
            ]);

            if (! $department->exists) {
                $this->db->select('select pg_advisory_xact_lock(?)', [MoveDepartment::TREE_LOCK]);

                $department->parent_id = $parent?->id;
                $department->save();

                $parentPath = $parent?->refresh()->path;
                $path = $parentPath === null ? (string) $department->id : "{$parentPath}.{$department->id}";

                Department::query()->whereKey($department->id)->update(['path' => $path, 'depth' => substr_count($path, '.')]);

                return $department->refresh();
            }

            $department->save();

            if ($department->parent_id !== $parent?->id) {
                return $this->move->handle($department, $parent);
            }

            return $department;
        });
    }
}
