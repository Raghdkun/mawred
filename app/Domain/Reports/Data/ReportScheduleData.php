<?php

declare(strict_types=1);

namespace App\Domain\Reports\Data;

use App\Domain\Reports\Contracts\Report;
use App\Domain\Reports\Enums\ReportFormat;
use App\Domain\Reports\Enums\RunStatus;
use App\Domain\Reports\Enums\ScheduleFrequency;
use App\Domain\Reports\Models\ReportSchedule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One of my scheduled reports (D120). */
#[TypeScript]
final class ReportScheduleData extends Data
{
    /**
     * @param  array<string, string|int|null>  $params
     */
    public function __construct(
        public string $id,
        public string $report,
        public string $title,
        public ReportFormat $format,
        public ScheduleFrequency $frequency,
        public ?int $weekday,
        public ?int $monthDay,
        public int $hour,
        public string $timezone,
        public array $params,
        public string $nextRunAt,
        public ?string $lastRunAt,
        public ?RunStatus $lastRunStatus,
        public bool $paused,
        public ?string $pausedReason,
    ) {}

    public static function fromSchedule(ReportSchedule $schedule, ?Report $report, string $locale): self
    {
        /** @var array<string, string|int|null> $params */
        $params = $schedule->params;

        return new self(
            $schedule->ulid,
            $schedule->report,
            $report instanceof Report ? $report->title()[$locale === 'ar' ? 'ar' : 'en'] : __('A report you can no longer open'),
            $schedule->format,
            $schedule->frequency,
            $schedule->weekday,
            $schedule->month_day,
            $schedule->hour,
            $schedule->timezone,
            $params,
            $schedule->next_run_at->toIso8601String(),
            $schedule->last_run_at?->toIso8601String(),
            $schedule->lastRun?->status,
            $schedule->paused_at !== null,
            $schedule->paused_reason,
        );
    }
}
