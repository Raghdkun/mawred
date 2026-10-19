<?php

declare(strict_types=1);

namespace App\Domain\Reports\Data;

use App\Domain\Reports\Contracts\Report;
use App\Domain\Reports\Enums\ReportCategory;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A report in the library (handoff 36): its name, what it shows, and whether it can be exported. */
#[TypeScript]
final class ReportCardData extends Data
{
    public function __construct(
        public string $key,
        public ReportCategory $category,
        public string $title,
        public string $description,
        public bool $canExport,
    ) {}

    public static function fromReport(Report $report, string $locale, bool $canExport): self
    {
        $pick = $locale === 'ar' ? 'ar' : 'en';

        return new self($report->key(), $report->category(), $report->title()[$pick], $report->description()[$pick], $canExport);
    }
}
