<?php

declare(strict_types=1);

namespace App\Domain\Leave\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a time-away request would take, checked before it is sent: working days (or minutes),
 * the balance before and after, and anything that would stop it.
 */
#[TypeScript]
final class LeaveQuoteData extends Data
{
    /**
     * @param  list<string>  $dates  working dates it covers
     * @param  list<array{code: string, message: string}>  $problems
     */
    public function __construct(
        public string $kind,
        public ?string $typeName,
        public string $unit,
        public ?string $from,
        public ?string $to,
        public float $amount,
        public array $dates,
        public ?float $available,
        public ?float $after,
        public bool $attachmentRequired,
        public array $problems,
    ) {}

    public function ok(): bool
    {
        return $this->problems === [];
    }
}
