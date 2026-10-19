<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Reports\Actions\PruneReportRuns;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reports:prune')]
#[Description('Delete report exports older than a week and fail exports a crashed worker left behind (D114).')]
final class ReportsPrune extends Command
{
    public function handle(PruneReportRuns $prune): int
    {
        $result = $prune->handle();
        $this->info("Deleted {$result['deleted']} export(s); marked {$result['stale']} stale export(s) as failed.");

        return self::SUCCESS;
    }
}
