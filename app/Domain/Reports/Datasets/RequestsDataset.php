<?php

declare(strict_types=1);

namespace App\Domain\Reports\Datasets;

use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Reports\Enums\ColumnType;
use App\Domain\Reports\Enums\ReportCategory;
use App\Domain\Requests\Models\EmployeeRequest;
use Illuminate\Database\Eloquent\Builder;

/** Requests people sent, with their type, outcome and how long a decision took (requests.view). *
 * @extends BaseDataset<EmployeeRequest>
 */
final class RequestsDataset extends BaseDataset
{
    public function key(): string
    {
        return 'requests';
    }

    public function category(): ReportCategory
    {
        return ReportCategory::Requests;
    }

    public function title(): array
    {
        return ['en' => 'Requests', 'ar' => 'الطلبات'];
    }

    public function description(): array
    {
        return ['en' => 'Every request sent, its type, status and time to a decision.', 'ar' => 'كل طلب مقدّم ونوعه وحالته والمدة حتى القرار.'];
    }

    public function permission(): string
    {
        return 'requests.view';
    }

    public function dateField(): string
    {
        return 'submitted_on';
    }

    public function branchColumn(): string
    {
        return 'requests.branch_id';
    }

    public function departmentColumn(): string
    {
        return 'requests.department_id';
    }

    public function fields(): array
    {
        return [
            DatasetField::text('number', 'Request no.', 'رقم الطلب', 'requests.number', groupable: false),
            DatasetField::text('employee_name', 'Requester', 'مقدّم الطلب', self::personName()),
            DatasetField::text('branch', 'Branch', 'الفرع', self::translated('rb.name')),
            DatasetField::text('department', 'Department', 'القسم', self::translated('rd.name')),
            DatasetField::text('type', 'Request type', 'نوع الطلب', self::translated('rrt.name')),
            DatasetField::choice('status', 'Status', 'الحالة', 'requests.status', self::options([
                'in_review' => ['In review', 'قيد المراجعة'], 'returned' => ['Returned', 'معاد'], 'approved' => ['Approved', 'معتمد'],
                'rejected' => ['Rejected', 'مرفوض'], 'completed' => ['Completed', 'مكتمل'], 'cancelled' => ['Cancelled', 'ملغى'],
            ])),
            DatasetField::date('submitted_on', 'Submitted on', 'تاريخ التقديم', self::day('requests.submitted_at')),
            DatasetField::text('month', 'Month', 'الشهر', "to_char(requests.submitted_at, 'YYYY-MM')"),
            DatasetField::date('decided_on', 'Decided on', 'تاريخ القرار', self::day('requests.decided_at')),
            DatasetField::number('hours_to_decide', 'Hours to decide', 'ساعات القرار', 'round((extract(epoch from (requests.decided_at - requests.submitted_at)) / 3600)::numeric, 1)', ColumnType::Decimal),
        ];
    }

    protected function base(): Builder
    {
        return EmployeeRequest::query()
            ->leftJoin('employees', 'employees.id', '=', 'requests.requester_employee_id')
            ->leftJoin('request_types as rrt', 'rrt.id', '=', 'requests.request_type_id')
            ->leftJoin('branches as rb', 'rb.id', '=', 'requests.branch_id')
            ->leftJoin('departments as rd', 'rd.id', '=', 'requests.department_id');
    }

    protected function subject(): SubjectBinding
    {
        return SubjectBinding::employee('requests.requester_employee_id');
    }
}
