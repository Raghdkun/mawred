<?php

declare(strict_types=1);

namespace App\Domain\Reports\Support;

use App\Domain\Reports\Enums\FilterOp;
use App\Domain\Reports\Enums\Measure;

/**
 * A custom report's validated definition (D118): which dataset, which columns, the filters,
 * an optional grouping with what to count or add up, the chart, and the sort. Only
 * `DefinitionValidator` builds one, so every field and condition has been checked.
 */
final readonly class ReportDefinition
{
    /**
     * @param  list<string>  $columns
     * @param  list<array{field: string, op: FilterOp, value: string|int|list<string>|array{from: string, to: string}}>  $filters
     * @param  'none'|'bar'|'line'  $chart
     * @param  'asc'|'desc'  $direction
     */
    public function __construct(
        public string $dataset,
        public array $columns,
        public array $filters,
        public ?string $groupBy,
        public Measure $measure,
        public ?string $measureField,
        public string $chart,
        public ?string $sort,
        public string $direction,
    ) {}

    public function grouped(): bool
    {
        return $this->groupBy !== null;
    }

    /**
     * Every field the definition reads, for the field-permission check.
     *
     * @return list<string>
     */
    public function fieldsUsed(): array
    {
        return array_values(array_unique(array_filter([
            ...$this->columns,
            ...array_column($this->filters, 'field'),
            $this->groupBy,
            $this->measureField,
        ])));
    }

    /**
     * The definition as it's stored.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'dataset' => $this->dataset,
            'columns' => $this->columns,
            'filters' => array_map(fn (array $f): array => ['field' => $f['field'], 'op' => $f['op']->value, 'value' => $f['value']], $this->filters),
            'groupBy' => $this->groupBy,
            'measure' => $this->measure->value,
            'measureField' => $this->measureField,
            'chart' => $this->chart,
            'sort' => $this->sort,
            'direction' => $this->direction,
        ];
    }
}
