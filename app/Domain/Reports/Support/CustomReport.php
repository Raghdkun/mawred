<?php

declare(strict_types=1);

namespace App\Domain\Reports\Support;

use App\Domain\Access\Models\User;
use App\Domain\Reports\Contracts\Report;
use App\Domain\Reports\Datasets\BaseDataset;
use App\Domain\Reports\Enums\FilterType;
use App\Domain\Reports\Enums\ReportCategory;
use App\Domain\Reports\Models\SavedReport;
use Illuminate\Database\Eloquent\Model;

/**
 * A saved custom report seen through the Report contract, so the viewer, exports and schedules
 * treat it like a prebuilt one. It is built for one viewer: their field permissions decide the
 * columns, and their scope the rows (D119).
 */
final readonly class CustomReport implements Report
{
    /**
     * @param  BaseDataset<covariant Model>  $dataset
     */
    public function __construct(
        public SavedReport $saved,
        public BaseDataset $dataset,
        public ReportDefinition $definition,
        private CustomReportRunner $runner,
    ) {}

    public function key(): string
    {
        return $this->saved->reportKey();
    }

    public function category(): ReportCategory
    {
        return $this->dataset->category();
    }

    public function title(): array
    {
        return ['en' => $this->saved->name, 'ar' => $this->saved->name];
    }

    public function description(): array
    {
        return $this->saved->description === null || $this->saved->description === ''
            ? $this->dataset->title()
            : ['en' => $this->saved->description, 'ar' => $this->saved->description];
    }

    public function permission(): string
    {
        return $this->dataset->permission();
    }

    public function filters(): array
    {
        return array_values(array_filter([
            $this->dataset->dateField() !== null ? new ReportFilter('period', FilterType::Period, default: 'any') : null,
            $this->dataset->branchColumn() !== null ? new ReportFilter('branch', FilterType::Branch) : null,
            $this->dataset->departmentColumn() !== null ? new ReportFilter('department', FilterType::Department) : null,
        ]));
    }

    public function columns(): array
    {
        return $this->runner->columns($this->dataset, $this->definition);
    }

    public function rows(User $user, ReportParams $params, string $locale): iterable
    {
        return $this->runner->rows($this->dataset, $this->definition, $user, $params, $locale);
    }

    public function summarise(array $rows, ReportParams $params, string $locale): ReportSummary
    {
        $ar = $locale === 'ar';

        if (! $this->definition->grouped()) {
            return new ReportSummary([['label' => $ar ? 'الصفوف' : 'Rows', 'value' => count($rows), 'format' => 'number']]);
        }

        $measure = $this->columns()[1];
        $values = array_map(fn (array $r): float => is_numeric($r['measure'] ?? null) ? (float) $r['measure'] : 0.0, $rows);

        return new ReportSummary(
            [
                ['label' => $ar ? 'المجموعات' : 'Groups', 'value' => count($rows), 'format' => 'number'],
                ['label' => $measure->labelIn($locale), 'value' => round($this->definition->measure->value === 'avg' && $values !== [] ? array_sum($values) / count($values) : array_sum($values), 2), 'format' => $measure->type->value],
            ],
            $this->definition->chart === 'none' || $rows === [] ? null : [
                'type' => $this->definition->chart === 'line' ? 'line' : 'bar',
                'x' => 'group',
                'series' => [['key' => 'measure', 'label' => $measure->labelIn($locale)]],
                'data' => array_map(fn (array $r): array => ['group' => $r['group'] ?? null, 'measure' => $r['measure'] ?? null], $rows),
            ],
        );
    }
}
