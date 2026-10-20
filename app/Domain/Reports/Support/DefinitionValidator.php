<?php

declare(strict_types=1);

namespace App\Domain\Reports\Support;

use App\Domain\Access\Models\User;
use App\Domain\Reports\Datasets\BaseDataset;
use App\Domain\Reports\Datasets\DatasetField;
use App\Domain\Reports\Datasets\DatasetRegistry;
use App\Domain\Reports\Enums\ColumnType;
use App\Domain\Reports\Enums\FilterOp;
use App\Domain\Reports\Enums\Measure;
use App\Support\Errors\DomainRuleViolation;

/**
 * Checks a custom report definition from the builder against its dataset (D118, D121): known
 * fields only, conditions that suit each field, values of the right shape, and no sensitive
 * field the person may not see. Errors come back keyed by the builder's controls.
 */
final readonly class DefinitionValidator
{
    public const int MAX_COLUMNS = 20;

    public const int MAX_FILTERS = 10;

    public function __construct(private DatasetRegistry $datasets) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function validate(array $input, User $user): ReportDefinition
    {
        $errors = [];
        $dataset = is_string($input['dataset'] ?? null) ? $this->datasets->find($input['dataset']) : null;

        if (! $dataset instanceof BaseDataset || ! $dataset->canUse($user)) {
            throw new DomainRuleViolation('reports.definition', __('Choose what to report on.'), ['dataset' => [__('Choose what to report on.')]]);
        }

        $field = function (mixed $key) use ($dataset, $user): ?DatasetField {
            $found = is_string($key) ? $dataset->field($key) : null;

            return $found instanceof DatasetField && $dataset->visible($user, $found) ? $found : null;
        };

        $groupBy = $input['groupBy'] ?? null;
        $groupField = $groupBy === null || $groupBy === '' ? null : $field($groupBy);

        if ($groupBy !== null && $groupBy !== '' && (! $groupField instanceof DatasetField || ! $groupField->groupable)) {
            $errors['groupBy'] = [__('Choose a field to group by.')];
        }

        $columns = [];

        foreach (is_array($input['columns'] ?? null) ? $input['columns'] : [] as $key) {
            if (is_string($key) && $field($key) instanceof DatasetField && ! in_array($key, $columns, true)) {
                $columns[] = $key;
            }
        }

        if (! $groupField instanceof DatasetField && $columns === []) {
            $errors['columns'] = [__('Choose at least one column.')];
        } elseif (count($columns) > self::MAX_COLUMNS) {
            $errors['columns'] = [__('Choose :count columns or fewer.', ['count' => self::MAX_COLUMNS])];
        }

        $filters = [];
        $rawFilters = is_array($input['filters'] ?? null) ? array_values($input['filters']) : [];

        if (count($rawFilters) > self::MAX_FILTERS) {
            $errors['filters'] = [__('Use :count filters or fewer.', ['count' => self::MAX_FILTERS])];
        }

        foreach (array_slice($rawFilters, 0, self::MAX_FILTERS) as $index => $raw) {
            $raw = is_array($raw) ? $raw : [];
            $target = $field($raw['field'] ?? null);
            $op = is_string($raw['op'] ?? null) ? FilterOp::tryFrom($raw['op']) : null;

            if (! $target instanceof DatasetField || ! $op instanceof FilterOp || ! in_array($op, $target->ops(), true)) {
                $errors["filters.{$index}"] = [__('Choose a field and a condition.')];

                continue;
            }

            $value = $this->value($target, $op, $raw['value'] ?? null);

            if ($value === null) {
                $errors["filters.{$index}.value"] = [__('Enter a value for this filter.')];

                continue;
            }

            $filters[] = ['field' => $target->key, 'op' => $op, 'value' => $value];
        }

        $measure = is_string($input['measure'] ?? null) ? (Measure::tryFrom($input['measure']) ?? Measure::Count) : Measure::Count;
        $measureField = null;

        if ($groupField instanceof DatasetField && $measure !== Measure::Count) {
            $target = $field($input['measureField'] ?? null);

            if (! $target instanceof DatasetField || ! $target->measurable) {
                $errors['measureField'] = [__('Choose a number to add up.')];
            } else {
                $measureField = $target->key;
            }
        }

        $chart = in_array($input['chart'] ?? null, ['bar', 'line'], true) && $groupField instanceof DatasetField ? (string) $input['chart'] : 'none';
        $sort = $input['sort'] ?? null;
        $sort = is_string($sort) && ($sort === 'measure' ? $groupField instanceof DatasetField : in_array($sort, $groupField instanceof DatasetField ? [$groupField->key] : $columns, true)) ? $sort : null;
        $direction = ($input['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        if ($errors !== []) {
            throw new DomainRuleViolation('reports.definition', (string) reset($errors)[0], $errors);
        }

        return new ReportDefinition(
            dataset: $dataset->key(),
            columns: $groupField instanceof DatasetField ? [] : $columns,
            filters: $filters,
            groupBy: $groupField?->key,
            measure: $groupField instanceof DatasetField ? $measure : Measure::Count,
            measureField: $measureField,
            chart: $chart,
            sort: $sort,
            direction: $direction,
        );
    }

    /**
     * A filter value in the shape its condition needs, or null.
     *
     * @return string|int|list<string>|array{from: string, to: string}|null
     */
    private function value(DatasetField $field, FilterOp $op, mixed $value): string|int|array|null
    {
        $date = fn (mixed $v): ?string => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1 && strtotime($v) !== false ? $v : null;
        $range = is_array($value) ? [$date($value['from'] ?? null), $date($value['to'] ?? null)] : [null, null];

        return match ($op) {
            FilterOp::In => is_array($value) && ($chosen = array_values(array_intersect(array_map(strval(...), array_filter($value, is_scalar(...))), array_column($field->options, 'value')))) !== [] ? $chosen : null,
            FilterOp::Between => $range[0] !== null && $range[1] !== null && $range[0] <= $range[1] ? ['from' => $range[0], 'to' => $range[1]] : null,
            FilterOp::LastDays => is_numeric($value) && (int) $value >= 1 && (int) $value <= 3660 ? (int) $value : null,
            FilterOp::Is, FilterOp::Contains => match (true) {
                $field->type === ColumnType::Text => is_string($value) && trim($value) !== '' && mb_strlen($value) <= 100 ? trim($value) : null,
                default => is_numeric($value) ? (string) (0 + $value) : null,
            },
            FilterOp::Gte, FilterOp::Lte => is_numeric($value) ? (string) (0 + $value) : null,
        };
    }
}
