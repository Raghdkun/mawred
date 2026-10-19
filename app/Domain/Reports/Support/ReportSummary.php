<?php

declare(strict_types=1);

namespace App\Domain\Reports\Support;

/**
 * What the viewer shows above the table (handoff 36): headline numbers and, where it helps, one
 * chart. Charts use the colourblind-safe palette and never mirror (D115 rules from the handoff).
 */
final readonly class ReportSummary
{
    /**
     * @param  list<array{label: string, value: int|float, format: string}>  $kpis
     * @param  array{type: 'bar'|'stacked'|'line', x: string, series: list<array{key: string, label: string}>, data: list<array<string, string|int|float|null>>}|null  $chart
     */
    public function __construct(public array $kpis = [], public ?array $chart = null) {}
}
