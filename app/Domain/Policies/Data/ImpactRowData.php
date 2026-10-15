<?php

declare(strict_types=1);

namespace App\Domain\Policies\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ImpactRowData extends Data
{
    /**
     * @param  list<ImpactChangeData>  $changes
     * @param  list<ImpactBalanceData>  $balances  leave earned before and after, for leave rule changes
     */
    public function __construct(
        public string $employeeId,
        public string $employeeNumber,
        public string $nameEn,
        public ?string $nameAr,
        public array $changes,
        public array $balances = [],
    ) {}
}
