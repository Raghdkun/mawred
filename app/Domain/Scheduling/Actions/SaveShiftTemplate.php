<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Actions;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Support\ReferenceValues;
use App\Domain\Scheduling\Data\ShiftTemplateInputData;
use App\Domain\Scheduling\Models\ShiftTemplate;

/** A reusable shift. Changing a template doesn't touch shifts already on rosters. */
final readonly class SaveShiftTemplate
{
    public function handle(ShiftTemplateInputData $input, ?ShiftTemplate $template = null): ShiftTemplate
    {
        $template ??= new ShiftTemplate;
        $code = $template->exists ? $template->code : ReferenceValues::code($input->code);

        if (! $template->exists) {
            ReferenceValues::assertCodeAvailable(ShiftTemplate::class, $code);
        }

        $template->setTranslations('name', ReferenceValues::translations($input->name));
        $template->fill([
            'code' => $code,
            'branch_id' => $input->branchId === null ? null : Branch::query()->where('ulid', $input->branchId)->value('id'),
            'starts_at' => $input->startsAt,
            'ends_at' => $input->endsAt,
            'break_minutes' => $input->breakMinutes,
            'color' => $input->color,
            'is_active' => $input->isActive,
        ])->save();

        return $template;
    }
}
