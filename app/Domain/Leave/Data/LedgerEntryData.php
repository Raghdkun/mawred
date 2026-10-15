<?php

declare(strict_types=1);

namespace App\Domain\Leave\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A line on a leave statement, with the running balance after it. */
#[TypeScript]
final class LedgerEntryData extends Data
{
    public function __construct(
        public string $id,
        public string $kind,
        public string $label,
        public float $amount,
        public float $runningBalance,
        public string $effectiveOn,
        public ?string $expiresOn,
        public ?string $note,
        public ?string $requestId,
        public ?string $requestNumber,
        public ?string $actor,
    ) {}
}
