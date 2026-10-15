<?php

declare(strict_types=1);

namespace App\Domain\Leave\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One leave type's balance for a leave year. `balance` is what the ledger holds; `reserved` is
 * held by pending requests (D64); `available` is what can still be requested.
 */
#[TypeScript]
final class LeaveBalanceData extends Data
{
    public function __construct(
        public string $typeId,
        public string $code,
        public string $name,
        public string $unit,
        public int $year,
        public float $entitlement,
        public float $earned,
        public float $carriedOver,
        public float $adjusted,
        public float $taken,
        public float $reserved,
        public float $balance,
        public float $available,
        public ?float $expiring = null,
        public ?string $expiresOn = null,
    ) {}
}
