<?php

declare(strict_types=1);

namespace App\Domain\Policies\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Leave earned over the change's year under the current and the proposed rule (handoff 20). */
#[TypeScript]
final class ImpactBalanceData extends Data
{
    /**
     * @param  array<string, string>  $name
     */
    public function __construct(
        public string $code,
        public array $name,
        public int $year,
        public float $before,
        public float $after,
        public float $delta,
    ) {}
}
