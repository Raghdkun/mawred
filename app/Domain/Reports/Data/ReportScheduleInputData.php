<?php

declare(strict_types=1);

namespace App\Domain\Reports\Data;

use App\Domain\Reports\Enums\ReportFormat;
use App\Domain\Reports\Enums\ScheduleFrequency;
use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A schedule for a report (D120): when it runs, as which file, and the filters to run it with.
 * Periods are stored as a relative range (last month, last 7 days…), so each run covers its own.
 */
#[TypeScript]
final class ReportScheduleInputData extends Data
{
    /**
     * @param  array<string, string|int|null>|null  $params  none for a report with nothing to filter
     */
    public function __construct(
        #[Max(60)]
        public string $report,
        public ReportFormat $format,
        public ScheduleFrequency $frequency,
        #[Between(1, 7)]
        public ?int $weekday,
        #[Between(1, 28)]
        public ?int $monthDay,
        #[Between(0, 23)]
        public int $hour,
        public ?array $params = null,
    ) {}
}
