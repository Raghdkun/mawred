<?php

declare(strict_types=1);

namespace App\Domain\Reports\Jobs;

use App\Domain\Reports\Actions\GenerateReportExport;
use App\Domain\Reports\Enums\RunStatus;
use App\Domain\Reports\Models\ReportRun;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;
use Throwable;

/** Makes one report export on the heavy queue; the run's status makes a retry harmless. */
#[Queue('reports')]
#[Tries(1)]
#[Timeout(900)]
#[UniqueFor(900)]
final class GenerateReportExportJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $runId) {}

    public function uniqueId(): string
    {
        return (string) $this->runId;
    }

    public function handle(GenerateReportExport $generate): void
    {
        $run = ReportRun::query()->find($this->runId);

        if ($run instanceof ReportRun) {
            $generate->handle($run);
        }
    }

    public function failed(?Throwable $exception): void
    {
        ReportRun::query()->whereKey($this->runId)->whereIn('status', [RunStatus::Queued->value, RunStatus::Running->value])
            ->update(['status' => RunStatus::Failed->value, 'error' => __('This export didn’t finish. Please try again.'), 'finished_at' => CarbonImmutable::now()]);
    }
}
