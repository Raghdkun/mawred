<?php

declare(strict_types=1);

namespace App\Domain\Reports\Datasets;

use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Reports\Enums\ColumnType;
use App\Domain\Reports\Enums\ReportCategory;
use Illuminate\Database\Eloquent\Builder;

/** Leave requests with their type, dates and status (leave.view). *
 * @extends BaseDataset<LeaveRequest>
 */
final class LeaveDataset extends BaseDataset
{
    public function key(): string
    {
        return 'leave';
    }

    public function category(): ReportCategory
    {
        return ReportCategory::Leave;
    }

    public function title(): array
    {
        return ['en' => 'Leave requests', 'ar' => 'طلبات الإجازة'];
    }

    public function description(): array
    {
        return ['en' => 'Leave, days off and hourly permissions, with their status.', 'ar' => 'الإجازات والأيام البديلة والاستئذانات بالساعة مع حالتها.'];
    }

    public function permission(): string
    {
        return 'leave.view';
    }

    public function dateField(): string
    {
        return 'starts_on';
    }

    public function fields(): array
    {
        return [
            ...self::personFields(),
            DatasetField::text('leave_type', 'Leave type', 'نوع الإجازة', self::translated('rlt.name')),
            DatasetField::choice('kind', 'Kind', 'النوع', 'leave_requests.kind', self::options([
                'leave' => ['Leave', 'إجازة'], 'day_off' => ['Day off', 'يوم بديل'], 'hourly' => ['Hourly', 'بالساعة'],
            ])),
            DatasetField::choice('status', 'Status', 'الحالة', 'leave_requests.status', self::options([
                'pending' => ['Pending', 'قيد الاعتماد'], 'approved' => ['Approved', 'معتمدة'],
                'rejected' => ['Rejected', 'مرفوضة'], 'cancelled' => ['Cancelled', 'ملغاة'],
            ])),
            DatasetField::choice('paid', 'Paid', 'مدفوعة', "case when rlt.is_paid then 'yes' else 'no' end", self::options(['yes' => ['Paid', 'مدفوعة'], 'no' => ['Unpaid', 'غير مدفوعة']])),
            DatasetField::date('starts_on', 'From', 'من', 'leave_requests.starts_on'),
            DatasetField::date('ends_on', 'To', 'إلى', 'leave_requests.ends_on'),
            DatasetField::text('month', 'Month', 'الشهر', "to_char(leave_requests.starts_on, 'YYYY-MM')"),
            DatasetField::number('amount', 'Amount (days or hours)', 'المدة (أيام أو ساعات)', 'leave_requests.amount', ColumnType::Decimal),
        ];
    }

    protected function base(): Builder
    {
        return LeaveRequest::query()
            ->join('employees', 'employees.id', '=', 'leave_requests.employee_id')
            ->leftJoin('leave_types as rlt', 'rlt.id', '=', 'leave_requests.leave_type_id')
            ->leftJoin('branches as rb', 'rb.id', '=', 'employees.current_branch_id')
            ->leftJoin('departments as rd', 'rd.id', '=', 'employees.current_department_id');
    }

    protected function subject(): SubjectBinding
    {
        return SubjectBinding::employee('leave_requests.employee_id');
    }
}
