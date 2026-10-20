<?php

declare(strict_types=1);

namespace App\Domain\Reports\Data;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the builder saves: a name and the definition. The definition is checked field by field
 * against its dataset by `DefinitionValidator`, not here.
 */
#[TypeScript]
final class CustomReportInputData extends Data
{
    /**
     * @param  array<string, mixed>  $definition
     */
    public function __construct(
        #[Min(1), Max(120)]
        public string $name,
        #[Max(300)]
        public ?string $description,
        public array $definition,
    ) {}
}
