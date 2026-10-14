<?php

declare(strict_types=1);

namespace App\Domain\Policies\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One level in a value's precedence chain (explainer popover, handoff 19).
 */
#[TypeScript]
final class ChainLinkData extends Data
{
    /**
     * @param  array<string, string>|null  $setName
     * @param  array<string, mixed>  $value
     */
    public function __construct(
        public string $level,
        public ?string $setId,
        public ?array $setName,
        public ?string $target,
        public array $value,
        public ?string $effectiveFrom,
        public bool $wins,
    ) {}
}
