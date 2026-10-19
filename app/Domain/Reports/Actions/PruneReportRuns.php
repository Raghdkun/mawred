<?php

declare(strict_types=1);

namespace App\Domain\Reports\Actions;

use App\Domain\Reports\Enums\RunStatus;
use App\Domain\Reports\Models\ReportRun;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes exports after their week (D114) and failed runs after the same time, and marks runs a
 * crashed worker left behind as failed so the person can try again.
 */
final readonly class PruneReportRuns
{
    /** A run still "running" after this long was lost with its worker. */
    public const int STALE_HOURS = 2;

    /**
     * @return array{deleted: int, stale: int}
     */
    public function handle(): array
    {
        $now = CarbonImmutable::now();
        $stale = ReportRun::query()->whereIn('status', [RunStatus::Queued->value, RunStatus::Running->value])
            ->where('created_at', '<', $now->subHours(self::STALE_HOURS))
            ->update(['status' => RunStatus::Failed->value, 'error' => __('This export didn’t finish. Please try again.'), 'finished_at' => $now]);
        $deleted = 0;

        ReportRun::query()
            ->where(fn (Builder $q) => $q->where('expires_at', '<', $now)
                ->orWhere(fn (Builder $q) => $q->where('status', RunStatus::Failed->value)->where('created_at', '<', $now->subDays(ReportRun::KEEP_DAYS))))
            ->orderBy('id')
            ->each(function (ReportRun $run) use (&$deleted): void {
                if ($run->file_path !== null) {
                    Storage::disk(ReportRun::DISK)->delete($run->file_path);
                }

                $run->delete();
                $deleted++;
            });

        return ['deleted' => $deleted, 'stale' => $stale];
    }
}
