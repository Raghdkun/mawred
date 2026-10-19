<?php

declare(strict_types=1);

namespace App\Domain\Reports\Data;

use App\Domain\Reports\Enums\FilterType;
use App\Domain\Reports\Support\ReportFilter;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One control of the viewer's filter bar. */
#[TypeScript]
final class ReportFilterData extends Data
{
    /**
     * @param  list<array{value: string, label: string}>  $options
     */
    public function __construct(
        public string $key,
        public FilterType $type,
        public bool $required,
        public ?string $label,
        public array $options,
    ) {}

    public static function fromFilter(ReportFilter $filter, string $locale): self
    {
        $pick = $locale === 'ar' ? 'ar' : 'en';

        return new self(
            $filter->key,
            $filter->type,
            $filter->required,
            $filter->label === null ? null : $filter->label[$pick],
            array_map(fn (array $option): array => ['value' => $option['value'], 'label' => $option['label'][$pick]], $filter->options),
        );
    }
}
