<?php

declare(strict_types=1);

namespace App\Domain\Reports\Data;

use App\Domain\Reports\Contracts\Report;
use App\Domain\Reports\Enums\ReportFormat;
use App\Domain\Reports\Enums\RunStatus;
use App\Domain\Reports\Models\ReportRun;
use App\Domain\Reports\Support\ReportParams;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One of my exports (D114): what it is, where it stands, and until when it can be downloaded. */
#[TypeScript]
final class ReportRunData extends Data
{
    public function __construct(
        public string $id,
        public string $report,
        public string $title,
        public string $period,
        public ReportFormat $format,
        public RunStatus $status,
        public ?int $rows,
        public ?string $fileName,
        public ?string $error,
        public string $createdAt,
        public ?string $expiresAt,
    ) {}

    public static function fromRun(ReportRun $run, ?Report $report, string $locale): self
    {
        /** @var array<string, string|int|null> $values */
        $values = $run->params;

        return new self(
            $run->ulid,
            $run->report,
            $report instanceof Report ? $report->title()[$locale === 'ar' ? 'ar' : 'en'] : $run->report,
            (new ReportParams($values))->summary($locale),
            $run->format,
            $run->status,
            $run->rows,
            $run->file_name,
            $run->error,
            $run->created_at->toIso8601String(),
            $run->expires_at?->toIso8601String(),
        );
    }
}
