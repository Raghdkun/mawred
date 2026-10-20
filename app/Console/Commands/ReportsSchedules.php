<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Reports\Actions\RunDueSchedules;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reports:schedules')]
#[Description('Start the scheduled reports that are due (D120).')]
final class ReportsSchedules extends Command
{
    public function handle(RunDueSchedules $run): int
    {
        $result = $run->handle();
        $this->info("Started {$result['started']} scheduled report(s); paused {$result['paused']}.");

        return self::SUCCESS;
    }
}
