<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Actions;

use App\Domain\Scheduling\Models\ShiftTemplate;

/** Retires a shift template; shifts made from it keep their times. */
final readonly class DeleteShiftTemplate
{
    public function handle(ShiftTemplate $template): void
    {
        $template->delete();
    }
}
