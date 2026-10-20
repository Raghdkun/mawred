<?php

declare(strict_types=1);

namespace App\Domain\Reports\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A KPI card on a home dashboard (D115, D123): its value, how it moved over the last months for
 * the sparkline, the change since last month, and the report behind it when the viewer can open
 * it. Labels come from the UI strings by `key`.
 */
#[TypeScript]
final class DashboardKpiData extends Data
{
    /**
     * @param  'number'|'percent'  $format
     * @param  list<int|float>  $trend  oldest first
     */
    public function __construct(
        public string $key,
        public int|float $value,
        public string $format,
        public array $trend,
        public int|float|null $delta,
        public ?string $href,
    ) {}
}
