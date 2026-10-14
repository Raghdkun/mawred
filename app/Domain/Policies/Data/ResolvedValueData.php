<?php

declare(strict_types=1);

namespace App\Domain\Policies\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The value of one key for one employee on one date, with where it came from. `level` is the
 * winning level, `branch_default` for the branch record (D46) or `default` when nothing set it.
 */
#[TypeScript]
final class ResolvedValueData extends Data
{
    /**
     * @param  array<string, mixed>  $value
     * @param  array<string, string>|null  $setName
     * @param  list<ChainLinkData>  $chain
     */
    public function __construct(
        public string $key,
        public string $section,
        public array $value,
        public string $level,
        public ?string $setId,
        public ?array $setName,
        public ?string $effectiveFrom,
        public array $chain,
    ) {}

    public function isOverride(): bool
    {
        return count(array_filter($this->chain, fn (ChainLinkData $link): bool => $link->level !== 'default')) > 1;
    }
}
