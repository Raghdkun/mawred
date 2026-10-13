<?php

declare(strict_types=1);

namespace App\Domain\People\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A custom field with this employee's value. `access` is 'hidden' for a sensitive field the
 * viewer cannot read (value is then always null).
 */
#[TypeScript]
final class CustomFieldValueData extends Data
{
    /**
     * @param  array<string, string>  $label
     * @param  array<string, string>  $help
     * @param  list<array{value: string, label: array<string, string>}>  $options
     */
    public function __construct(
        public string $key,
        public array $label,
        public array $help,
        public string $type,
        public string $section,
        public array $options,
        public bool $isRequired,
        public bool $isSensitive,
        public string $access,
        public mixed $value,
    ) {}
}
