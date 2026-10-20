<?php

declare(strict_types=1);

namespace App\Domain\Reports\Support;

use App\Domain\Access\Models\User;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\Reports\Datasets\BaseDataset;
use App\Domain\Reports\Datasets\DatasetField;
use App\Domain\Reports\Enums\ColumnType;
use App\Domain\Reports\Enums\FilterOp;
use App\Domain\Reports\Enums\Measure;
use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Runs a validated custom report definition on its dataset for one viewer: the dataset's scoped
 * query, the viewer's period, branch and department, the definition's filters (every value a
 * binding), then either the chosen columns or one row per group with its count or total.
 */
final readonly class CustomReportRunner
{
    /** A grouped report shows at most this many groups. */
    public const int MAX_GROUPS = 500;

    /** Column aliases, one per column a definition may have (DefinitionValidator::MAX_COLUMNS). */
    private const array ALIASES = [
        'report_c0', 'report_c1', 'report_c2', 'report_c3', 'report_c4', 'report_c5', 'report_c6', 'report_c7', 'report_c8', 'report_c9',
        'report_c10', 'report_c11', 'report_c12', 'report_c13', 'report_c14', 'report_c15', 'report_c16', 'report_c17', 'report_c18', 'report_c19',
    ];

    /**
     * The columns the viewer gets: the chosen fields, or the group and its measure.
     *
     * @param  BaseDataset<covariant Model>  $dataset
     * @return list<ReportColumn>
     */
    public function columns(BaseDataset $dataset, ReportDefinition $definition): array
    {
        if ($definition->grouped()) {
            $group = $this->field($dataset, (string) $definition->groupBy);

            return [
                new ReportColumn('group', $group->label, $group->type),
                new ReportColumn('measure', $this->measureLabel($dataset, $definition), $this->measureType($dataset, $definition)),
            ];
        }

        return array_map(fn (string $key): ReportColumn => new ReportColumn($key, $this->field($dataset, $key)->label, $this->field($dataset, $key)->type), $definition->columns);
    }

    /**
     * @param  BaseDataset<covariant Model>  $dataset
     * @return Generator<int, array<string, string|int|float|null>>
     */
    public function rows(BaseDataset $dataset, ReportDefinition $definition, User $user, ReportParams $params, string $locale): Generator
    {
        $query = $this->filtered($dataset, $definition, $user, $params, $locale)->toBase();

        if ($definition->grouped()) {
            $group = $this->field($dataset, (string) $definition->groupBy);
            $expression = $group->sql($locale);
            $measure = match ($definition->measure) {
                Measure::Count => 'count(*)',
                Measure::Sum => 'coalesce(sum('.$this->field($dataset, (string) $definition->measureField)->sql($locale).'), 0)',
                Measure::Avg => 'avg('.$this->field($dataset, (string) $definition->measureField)->sql($locale).')',
            };
            $query->selectRaw("({$expression}) as report_group, {$measure} as report_measure")
                ->groupByRaw("({$expression})")
                ->orderByRaw($definition->sort === 'measure' ? 'report_measure '.$definition->direction : 'report_group '.$definition->direction.' nulls last')
                ->limit(self::MAX_GROUPS);

            foreach ($query->get() as $row) {
                yield [
                    'group' => $group->display($row->report_group ?? null, $locale) ?? '—',
                    'measure' => is_numeric($row->report_measure ?? null) ? round((float) $row->report_measure, 2) + 0 : 0,
                ];
            }

            return;
        }

        $fields = array_map(fn (string $key): DatasetField => $this->field($dataset, $key), $definition->columns);

        foreach ($fields as $index => $field) {
            $query->addSelect(DB::raw("({$field->sql($locale)}) as ".self::ALIASES[$index]));
        }

        $sortIndex = $definition->sort === null ? 0 : (int) array_search($definition->sort, $definition->columns, true);
        $query->orderBy(DB::raw(self::ALIASES[$sortIndex] ?? self::ALIASES[0]), $definition->direction);

        foreach ($query->cursor() as $row) {
            $values = (array) $row;
            $out = [];

            foreach ($fields as $index => $field) {
                $out[$field->key] = $field->display($values[self::ALIASES[$index]] ?? null, $locale);
            }

            yield $out;
        }
    }

    /**
     * @param  BaseDataset<covariant Model>  $dataset
     * @return Builder<covariant Model>
     */
    private function filtered(BaseDataset $dataset, ReportDefinition $definition, User $user, ReportParams $params, string $locale): Builder
    {
        $query = $dataset->query($user);
        $branch = $params->branch();
        $department = $params->department();

        if ($branch instanceof Branch && $dataset->branchColumn() !== null) {
            $query->where($dataset->branchColumn(), $branch->id);
        }

        if ($department instanceof Department && $dataset->departmentColumn() !== null) {
            $query->whereIn($dataset->departmentColumn(), Department::query()->select('id')->whereRaw('path <@ ?::ltree', [$department->path]));
        }

        $dateField = $dataset->dateField() === null ? null : $dataset->field($dataset->dateField());

        if ($dateField instanceof DatasetField && $params->string('from') !== null) {
            $query->whereRaw("({$dateField->sql($locale)}) between ? and ?", [$params->from(), $params->to()]);
        }

        foreach ($definition->filters as $filter) {
            $expression = '('.$this->field($dataset, $filter['field'])->sql($locale).')';
            $value = $filter['value'];

            match ($filter['op']) {
                FilterOp::Is => $query->whereRaw("{$expression} = ?", [$value]),
                FilterOp::Contains => $query->whereRaw("{$expression} ilike ?", ['%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], is_string($value) ? $value : '').'%']),
                FilterOp::In => $query->whereIn(DB::raw($expression), array_values((array) $value)),
                FilterOp::Between => $query->whereRaw("{$expression} between ? and ?", is_array($value) && isset($value['from'], $value['to']) ? [$value['from'], $value['to']] : ['', '']),
                FilterOp::LastDays => $query->whereRaw("{$expression} between ? and ?", [
                    CarbonImmutable::parse($params->today())->subDays(max(0, (is_int($value) ? $value : 1) - 1))->toDateString(), $params->today(),
                ]),
                FilterOp::Gte => $query->whereRaw("{$expression} >= ?", [$value]),
                FilterOp::Lte => $query->whereRaw("{$expression} <= ?", [$value]),
            };
        }

        return $query;
    }

    /** @param  BaseDataset<covariant Model>  $dataset */
    private function field(BaseDataset $dataset, string $key): DatasetField
    {
        return $dataset->field($key) ?? throw new \LogicException("Unknown field {$key} on {$dataset->key()}");
    }

    /**
     * @param  BaseDataset<covariant Model>  $dataset
     * @return array{en: string, ar: string}
     */
    private function measureLabel(BaseDataset $dataset, ReportDefinition $definition): array
    {
        if ($definition->measure === Measure::Count || $definition->measureField === null) {
            return ['en' => 'Count', 'ar' => 'العدد'];
        }

        $label = $this->field($dataset, $definition->measureField)->label;

        return $definition->measure === Measure::Sum
            ? ['en' => "Total {$label['en']}", 'ar' => "إجمالي {$label['ar']}"]
            : ['en' => "Average {$label['en']}", 'ar' => "متوسط {$label['ar']}"];
    }

    /** @param  BaseDataset<covariant Model>  $dataset */
    private function measureType(BaseDataset $dataset, ReportDefinition $definition): ColumnType
    {
        if ($definition->measure === Measure::Count || $definition->measureField === null) {
            return ColumnType::Number;
        }

        $type = $this->field($dataset, $definition->measureField)->type;

        return $definition->measure === Measure::Avg && $type === ColumnType::Number ? ColumnType::Decimal : $type;
    }
}
