<?php

declare(strict_types=1);

namespace App\Domain\Reports\Data;

use App\Domain\Reports\Datasets\DatasetField;
use App\Domain\Reports\Enums\ColumnType;
use App\Domain\Reports\Enums\FilterOp;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A field the builder offers: its label, type, choices, and what it can be used for. */
#[TypeScript]
final class DatasetFieldData extends Data
{
    /**
     * @param  list<array{value: string, label: string}>  $options
     * @param  list<FilterOp>  $ops
     */
    public function __construct(
        public string $key,
        public string $label,
        public ColumnType $type,
        public array $options,
        public bool $groupable,
        public bool $measurable,
        public array $ops,
    ) {}

    public static function fromField(DatasetField $field, string $locale): self
    {
        $pick = $locale === 'ar' ? 'ar' : 'en';

        return new self(
            $field->key,
            $field->labelIn($locale),
            $field->type,
            array_map(fn (array $o): array => ['value' => $o['value'], 'label' => $o['label'][$pick]], $field->options),
            $field->groupable,
            $field->measurable,
            $field->ops(),
        );
    }
}
