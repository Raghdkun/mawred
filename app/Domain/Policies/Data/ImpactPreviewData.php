<?php

declare(strict_types=1);

namespace App\Domain\Policies\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Who a proposed policy change affects and how (handoff 20), with leave balance deltas for
 * leave rule changes.
 */
#[TypeScript]
final class ImpactPreviewData extends Data
{
    /**
     * @param  list<string>  $keys
     * @param  list<ImpactRowData>  $rows  the first affected employees, by name
     */
    public function __construct(
        public string $date,
        public array $keys,
        public int $checked,
        public int $affected,
        public array $rows,
    ) {}
}
