<?php

declare(strict_types=1);

namespace App\Domain\Policies\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One key of a set in the editor: its value on the editor date (null = the set doesn't define it)
 * and changes already scheduled after that date.
 */
#[TypeScript]
final class PolicySetValueData extends Data
{
    /**
     * @param  array<string, mixed>|null  $value
     * @param  list<array{value: array<string, mixed>|null, effectiveFrom: string}>  $scheduled
     */
    public function __construct(
        public string $key,
        public ?array $value,
        public ?string $effectiveFrom,
        public array $scheduled,
    ) {}
}
