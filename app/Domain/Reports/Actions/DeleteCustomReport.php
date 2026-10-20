<?php

declare(strict_types=1);

namespace App\Domain\Reports\Actions;

use App\Domain\Reports\Models\ReportSchedule;
use App\Domain\Reports\Models\SavedReport;
use App\Support\Actors\Actor;
use Illuminate\Database\ConnectionInterface;

/** Deletes a saved report with its shares and any schedules that run it. Past exports stay. */
final readonly class DeleteCustomReport
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(Actor $actor, SavedReport $report): void
    {
        $this->db->transaction(function () use ($report): void {
            ReportSchedule::query()->where('report', $report->reportKey())->delete();
            $report->delete();
        });
    }
}
