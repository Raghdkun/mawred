<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Data\PositionInputData;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Grade;
use App\Domain\Organization\Models\Position;
use App\Domain\Organization\Support\ReferenceValues;

final readonly class SavePosition
{
    public function handle(PositionInputData $input, ?Position $position = null): Position
    {
        $position ??= new Position;
        $code = ReferenceValues::code($input->code);

        ReferenceValues::assertCodeAvailable(Position::class, $code, $position);

        $position->setTranslations('title', ReferenceValues::translations($input->title));
        $position->fill([
            'code' => $code,
            'department_id' => $input->departmentId === null ? null : Department::query()->where('ulid', $input->departmentId)->value('id'),
            'grade_id' => $input->gradeId === null ? null : Grade::query()->where('ulid', $input->gradeId)->value('id'),
            'is_active' => $input->isActive,
        ]);
        $position->save();

        return $position;
    }
}
