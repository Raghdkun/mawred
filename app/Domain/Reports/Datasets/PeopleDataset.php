<?php

declare(strict_types=1);

namespace App\Domain\Reports\Datasets;

use App\Domain\Access\Support\SubjectBinding;
use App\Domain\People\Models\Employee;
use App\Domain\Reports\Enums\ColumnType;
use App\Domain\Reports\Enums\ReportCategory;
use Illuminate\Database\Eloquent\Builder;

/** Employees with their current placement (people.view). *
 * @extends BaseDataset<Employee>
 */
final class PeopleDataset extends BaseDataset
{
    public function key(): string
    {
        return 'people';
    }

    public function category(): ReportCategory
    {
        return ReportCategory::People;
    }

    public function title(): array
    {
        return ['en' => 'People', 'ar' => 'الموظفون'];
    }

    public function description(): array
    {
        return ['en' => 'Everyone on the employee record, with their job and placement.', 'ar' => 'كل الموظفين في السجل مع وظائفهم ومواقعهم.'];
    }

    public function permission(): string
    {
        return 'people.view';
    }

    public function dateField(): string
    {
        return 'hire_date';
    }

    public function fields(): array
    {
        return [
            ...self::personFields(),
            DatasetField::text('position', 'Position', 'المسمى الوظيفي', self::translated('rp.title')),
            DatasetField::text('employment_type', 'Employment type', 'نوع التوظيف', self::translated('ret.name')),
            DatasetField::choice('status', 'Status', 'الحالة', 'employees.status', self::options([
                'pending' => ['Starting soon', 'لم يباشر بعد'], 'active' => ['Active', 'على رأس العمل'],
                'suspended' => ['Suspended', 'موقوف'], 'terminated' => ['Left', 'غادر'],
            ])),
            DatasetField::choice('gender', 'Gender', 'الجنس', 'employees.gender', self::options(['female' => ['Female', 'أنثى'], 'male' => ['Male', 'ذكر']])),
            DatasetField::text('nationality', 'Nationality', 'الجنسية', 'employees.nationality'),
            DatasetField::date('hire_date', 'Hire date', 'تاريخ التعيين', 'employees.hire_date'),
            DatasetField::text('hire_month', 'Hire month', 'شهر التعيين', "to_char(employees.hire_date, 'YYYY-MM')"),
            DatasetField::date('probation_ends_on', 'Probation ends', 'نهاية التجربة', 'employees.probation_ends_on'),
            DatasetField::date('termination_date', 'Leaving date', 'تاريخ المغادرة', 'employees.termination_date'),
            DatasetField::number('years_of_service', 'Years of service', 'سنوات الخدمة', 'round((extract(epoch from age(coalesce(employees.termination_date, current_date), employees.hire_date)) / 31557600)::numeric, 1)', ColumnType::Decimal),
            DatasetField::date('date_of_birth', 'Date of birth', 'تاريخ الميلاد', 'employees.date_of_birth', 'employee.date_of_birth'),
            DatasetField::date('passport_expires_on', 'Passport expires', 'انتهاء جواز السفر', 'employees.passport_expires_on', 'employee.passport'),
            DatasetField::date('residency_expires_on', 'Residency expires', 'انتهاء الإقامة', 'employees.residency_expires_on', 'employee.residency'),
        ];
    }

    protected function base(): Builder
    {
        return Employee::query()
            ->leftJoin('branches as rb', 'rb.id', '=', 'employees.current_branch_id')
            ->leftJoin('departments as rd', 'rd.id', '=', 'employees.current_department_id')
            ->leftJoin('positions as rp', 'rp.id', '=', 'employees.current_position_id')
            ->leftJoin('employment_types as ret', 'ret.id', '=', 'employees.current_employment_type_id');
    }

    protected function subject(): SubjectBinding
    {
        return SubjectBinding::employee('employees.id');
    }
}
