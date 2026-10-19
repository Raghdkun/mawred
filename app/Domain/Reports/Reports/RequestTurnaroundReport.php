<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Reports\Enums\ColumnType;
use App\Domain\Reports\Enums\FilterType;
use App\Domain\Reports\Enums\ReportCategory;
use App\Domain\Reports\Support\BaseReport;
use App\Domain\Reports\Support\Instants;
use App\Domain\Reports\Support\ReportColumn;
use App\Domain\Reports\Support\ReportFilter;
use App\Domain\Reports\Support\ReportParams;
use App\Domain\Reports\Support\ReportSummary;
use App\Domain\Requests\Enums\RequestStatus;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Workflows\Enums\TaskStatus;

/**
 * Requests submitted in a period by type: how many, how they ended, how long a decision took and
 * how many approval steps were decided before they were due.
 */
final class RequestTurnaroundReport extends BaseReport
{
    public function key(): string
    {
        return 'requests.turnaround';
    }

    public function category(): ReportCategory
    {
        return ReportCategory::Requests;
    }

    public function title(): array
    {
        return ['en' => 'Requests and turnaround', 'ar' => 'الطلبات ومدة الإنجاز'];
    }

    public function description(): array
    {
        return ['en' => 'Request volume by type, outcomes and time to a decision.', 'ar' => 'عدد الطلبات حسب النوع ونتائجها والمدة حتى القرار.'];
    }

    public function permission(): string
    {
        return 'requests.view';
    }

    public function filters(): array
    {
        return [new ReportFilter('period', FilterType::Period), ...self::placementFilters()];
    }

    public function columns(): array
    {
        return [
            ReportColumn::text('type', 'Request type', 'نوع الطلب'),
            ReportColumn::number('submitted', 'Submitted', 'المقدّمة'),
            ReportColumn::number('approved', 'Approved', 'المعتمدة'),
            ReportColumn::number('rejected', 'Rejected', 'المرفوضة'),
            ReportColumn::number('open', 'Open', 'المفتوحة'),
            ReportColumn::number('average_hours', 'Average hours to decide', 'متوسط ساعات القرار', ColumnType::Decimal),
            ReportColumn::number('on_time', 'Steps on time', 'الخطوات في الموعد', ColumnType::Percent),
        ];
    }

    public function rows(User $user, ReportParams $params, string $locale): iterable
    {
        [$start, $end] = Instants::between($params->from(), $params->to());
        $approved = [RequestStatus::Approved->value, RequestStatus::Completed->value];
        $open = [RequestStatus::InReview->value, RequestStatus::Returned->value];
        $requests = $this->placed($this->scoped(EmployeeRequest::query(), $user, SubjectBinding::employee('requests.requester_employee_id')), $params, 'requests.branch_id', 'requests.department_id')
            ->where('requests.submitted_at', '>=', $start)
            ->where('requests.submitted_at', '<', $end);
        $tasks = (clone $requests)->toBase()
            ->join('workflow_tasks', 'workflow_tasks.workflow_instance_id', '=', 'requests.workflow_instance_id')
            ->where('workflow_tasks.status', TaskStatus::Done->value)
            ->whereNotNull('workflow_tasks.due_at')
            ->selectRaw('requests.request_type_id, count(*) as due, count(*) filter (where workflow_tasks.decided_at <= workflow_tasks.due_at) as on_time')
            ->groupBy('requests.request_type_id')
            ->get()->keyBy('request_type_id');
        $query = $requests->toBase()
            ->join('request_types', 'request_types.id', '=', 'requests.request_type_id')
            ->select(['requests.request_type_id', 'request_types.name'])
            ->selectRaw('count(*) as submitted')
            ->selectRaw('count(*) filter (where requests.status in (?, ?)) as approved', $approved)
            ->selectRaw('count(*) filter (where requests.status = ?) as rejected', [RequestStatus::Rejected->value])
            ->selectRaw('count(*) filter (where requests.status in (?, ?)) as open', $open)
            ->selectRaw('avg(extract(epoch from (requests.decided_at - requests.submitted_at)) / 3600) filter (where requests.decided_at is not null) as average_hours')
            ->groupBy('requests.request_type_id', 'request_types.name', 'request_types.sort_order')
            ->orderBy('request_types.sort_order');

        foreach ($query->cursor() as $row) {
            $sla = $tasks->get(self::int($row->request_type_id));

            yield [
                'type' => self::name($row->name, $locale),
                'submitted' => self::int($row->submitted) ?? 0,
                'approved' => self::int($row->approved) ?? 0,
                'rejected' => self::int($row->rejected) ?? 0,
                'open' => self::int($row->open) ?? 0,
                'average_hours' => $row->average_hours === null ? null : round(self::float($row->average_hours), 1),
                'on_time' => $sla === null ? null : self::percent(self::int($sla->on_time) ?? 0, self::int($sla->due) ?? 0),
            ];
        }
    }

    public function summarise(array $rows, ReportParams $params, string $locale): ReportSummary
    {
        $ar = $locale === 'ar';
        $submitted = (int) array_sum(array_column($rows, 'submitted'));
        $decided = array_filter($rows, fn (array $r): bool => $r['average_hours'] !== null);
        $weights = array_sum(array_map(fn (array $r): int => (int) $r['approved'] + (int) $r['rejected'], $decided));
        $average = $weights > 0 ? round(array_sum(array_map(fn (array $r): float => (float) $r['average_hours'] * ((int) $r['approved'] + (int) $r['rejected']), $decided)) / $weights, 1) : 0.0;

        return new ReportSummary(
            [
                ['label' => $ar ? 'المقدّمة' : 'Submitted', 'value' => $submitted, 'format' => 'number'],
                ['label' => $ar ? 'نسبة الاعتماد' : 'Approval rate', 'value' => self::percent((int) array_sum(array_column($rows, 'approved')), $submitted), 'format' => 'percent'],
                ['label' => $ar ? 'متوسط ساعات القرار' : 'Average hours to decide', 'value' => $average, 'format' => 'decimal'],
            ],
            $rows === [] ? null : ['type' => 'stacked', 'x' => 'type', 'series' => [
                ['key' => 'approved', 'label' => $ar ? 'المعتمدة' : 'Approved'],
                ['key' => 'rejected', 'label' => $ar ? 'المرفوضة' : 'Rejected'],
                ['key' => 'open', 'label' => $ar ? 'المفتوحة' : 'Open'],
            ], 'data' => array_map(fn (array $r): array => ['type' => $r['type'], 'approved' => $r['approved'], 'rejected' => $r['rejected'], 'open' => $r['open']], $rows)],
        );
    }
}
