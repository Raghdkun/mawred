<?php

declare(strict_types=1);

namespace App\Domain\Reports\Data;

use App\Domain\Reports\Enums\ColumnType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the viewer shows for one set of filters: headline numbers, an optional chart and the first
 * rows of the table. `truncated` means there are more rows than the screen reads (export for all).
 */
#[TypeScript]
final class ReportResultData extends Data
{
    /**
     * @param  list<array{key: string, label: string, type: ColumnType}>  $columns
     * @param  list<array<string, string|int|float|null>>  $rows
     * @param  list<array{label: string, value: int|float, format: string}>  $kpis
     * @param  array{type: string, x: string, series: list<array{key: string, label: string}>, data: list<array<string, string|int|float|null>>}|null  $chart
     */
    public function __construct(
        public array $columns,
        public array $rows,
        public int $total,
        public bool $truncated,
        public array $kpis,
        public ?array $chart,
    ) {}
}
