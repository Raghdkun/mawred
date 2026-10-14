<?php

declare(strict_types=1);

namespace App\Domain\Policies\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ImpactChangeData extends Data
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function __construct(
        public string $key,
        public array $before,
        public array $after,
        public string $beforeLevel,
        public string $afterLevel,
    ) {}
}
