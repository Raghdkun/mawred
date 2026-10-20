<?php

declare(strict_types=1);

namespace App\Domain\Reports\Actions;

use App\Domain\Reports\Models\ReportSchedule;
use App\Support\Actors\Actor;

/** Deletes a schedule; files it already made stay in My exports for their week. */
final readonly class DeleteReportSchedule
{
    public function handle(Actor $actor, ReportSchedule $schedule): void
    {
        $schedule->delete();
    }
}
