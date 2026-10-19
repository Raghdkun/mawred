<?php

declare(strict_types=1);

namespace App\Domain\Requests\Actions;

use App\Domain\Forms\Actions\PublishForm;
use App\Domain\Forms\Actions\SaveFormDraft;
use App\Domain\Forms\Actions\SaveFormSettings;
use App\Domain\Forms\Data\FormSettingsData;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Policies\Models\LeaveType;
use App\Domain\Requests\Enums\RequestCategory;
use App\Domain\Requests\Models\RequestType;
use App\Domain\Workflows\Actions\PublishWorkflow;
use App\Domain\Workflows\Actions\SaveWorkflowDefinition;
use App\Domain\Workflows\Actions\SaveWorkflowDraft;
use App\Domain\Workflows\Models\WorkflowDefinition;
use App\Support\Actors\Actor;
use Illuminate\Database\ConnectionInterface;

/**
 * The 15 seeded request types (D55) with their forms and workflows (D56): manager then the
 * branch HR officer; letters go to HR only; resignation and salary advance add the department
 * head; details changes skip the manager (bank data) and apply on approval. Types whose module
 * ships later are seeded but flagged. Idempotent: an existing code is left as HR edited it.
 */
final readonly class SeedRequestTypes
{
    private const array SLA = ['hours' => 48, 'reminderHours' => 24, 'escalateTo' => ['type' => 'assignee_manager']];

    public function __construct(
        private ConnectionInterface $db,
        private SaveFormSettings $formSettings,
        private SaveFormDraft $formDraft,
        private PublishForm $publishForm,
        private SaveWorkflowDefinition $workflowDefinition,
        private SaveWorkflowDraft $workflowDraft,
        private PublishWorkflow $publishWorkflow,
    ) {}

    public function handle(): void
    {
        $actor = Actor::system('seed');

        foreach (self::catalog() as $index => $type) {
            $existing = RequestType::withTrashed()->where('code', $type['code'])->first();

            if ($existing instanceof RequestType) {
                $this->upgrade($actor, $existing, $type);

                continue;
            }

            $this->db->transaction(function () use ($actor, $type, $index): void {
                $form = $this->formSettings->handle($actor, FormSettingsData::from(['code' => $type['code'], 'name' => $type['name'], 'category' => 'requests']));
                $this->formDraft->handle($actor, $form, ['format' => 'mawred.form/v1', 'pages' => [[
                    'key' => 'main', 'title' => $type['name'], 'sections' => [['key' => 'details', 'title' => null, 'fields' => $type['fields']]],
                ]]]);
                $this->publishForm->handle($actor, $form);

                $flow = $this->workflowDefinition->handle($actor, $type['code'], $type['name'], [], 'requests');
                $this->workflowDraft->handle($actor, $flow, self::graph($type['chain'], $type['update'] ?? null));
                $this->publishWorkflow->handle($actor, $flow, array_map(fn (array $field): string => FieldSpec::string($field['key'] ?? null), $type['fields']));

                RequestType::query()->create([
                    'code' => $type['code'],
                    'name' => $type['name'],
                    'description' => $type['description'],
                    'category' => $type['category'],
                    'icon' => $type['icon'],
                    'is_sensitive' => $type['sensitive'] ?? false,
                    'available_in_phase' => $type['phase'] ?? 0,
                    'behaviour' => isset($type['leave']) ? 'leave' : ($type['behaviour'] ?? null),
                    'leave_type_id' => isset($type['leave']) ? LeaveType::query()->where('code', $type['leave'])->value('id') : null,
                    'sort_order' => ($index + 1) * 10,
                    'form_id' => $form->id,
                    'workflow_definition_id' => $flow->id,
                ]);
            });
        }
    }

    /**
     * A type seeded while its module was still to come (D55) gets its shipped form and workflow
     * when the module arrives; types that were already live are never touched again.
     *
     * @param  array{code: string, chain: 'standard'|'hr'|'extended'|'hr_update'|'swap', fields: list<array<string, mixed>>, name: array<string, string>, phase?: int, update?: string, leave?: string, behaviour?: string}  $type
     */
    private function upgrade(Actor $actor, RequestType $existing, array $type): void
    {
        if ($existing->available_in_phase === 0 || ($type['phase'] ?? 0) !== 0) {
            return;
        }

        $this->db->transaction(function () use ($actor, $existing, $type): void {
            $form = $existing->form()->first();
            $flow = $existing->workflow()->first();

            if ($form instanceof Form) {
                $this->formDraft->handle($actor, $form, ['format' => 'mawred.form/v1', 'pages' => [[
                    'key' => 'main', 'title' => $type['name'], 'sections' => [['key' => 'details', 'title' => null, 'fields' => $type['fields']]],
                ]]]);
                $this->publishForm->handle($actor, $form);
            }

            if ($flow instanceof WorkflowDefinition) {
                $this->workflowDraft->handle($actor, $flow, self::graph($type['chain'], $type['update'] ?? null));
                $this->publishWorkflow->handle($actor, $flow, array_map(fn (array $field): string => FieldSpec::string($field['key'] ?? null), $type['fields']));
            }

            $existing->forceFill([
                'available_in_phase' => 0,
                'behaviour' => isset($type['leave']) ? 'leave' : ($type['behaviour'] ?? null),
                'leave_type_id' => isset($type['leave']) ? LeaveType::query()->where('code', $type['leave'])->value('id') : null,
            ])->save();
        });
    }

    /**
     * Start → approvals in order → (record update) → End, with any rejection ending as rejected.
     *
     * @param  'standard'|'hr'|'extended'|'hr_update'|'swap'  $chain
     * @return array<string, mixed>
     */
    public static function graph(string $chain, ?string $update = null): array
    {
        $hr = ['type' => 'role', 'role' => 'hr_officer', 'scoped' => true];
        $steps = match ($chain) {
            'hr', 'hr_update' => [['hr', ['en' => 'HR review', 'ar' => 'مراجعة الموارد البشرية'], $hr]],
            'extended' => [
                ['manager', ['en' => 'Manager approval', 'ar' => 'موافقة المدير'], ['type' => 'manager']],
                ['department', ['en' => 'Department head approval', 'ar' => 'موافقة رئيس القسم'], ['type' => 'department_head']],
                ['hr', ['en' => 'HR review', 'ar' => 'مراجعة الموارد البشرية'], $hr],
            ],
            'swap' => [
                ['colleague', ['en' => 'Colleague accepts', 'ar' => 'موافقة الزميل'], ['type' => 'field', 'field' => 'colleague']],
                ['manager', ['en' => 'Manager approval', 'ar' => 'موافقة المدير'], ['type' => 'manager']],
            ],
            default => [
                ['manager', ['en' => 'Manager approval', 'ar' => 'موافقة المدير'], ['type' => 'manager']],
                ['hr', ['en' => 'HR review', 'ar' => 'مراجعة الموارد البشرية'], $hr],
            ],
        };

        $nodes = [['id' => 'start', 'type' => 'start', 'config' => [], 'position' => ['x' => 0, 'y' => 120]]];
        $edges = [];
        $previous = 'start';
        $handle = null;

        foreach ($steps as $i => [$id, $label, $assignee]) {
            $nodes[] = ['id' => $id, 'type' => 'approval', 'position' => ['x' => 240 * ($i + 1), 'y' => 120], 'config' => [
                'label' => $label, 'assignee' => $assignee, 'mode' => 'any', 'sla' => self::SLA, 'allowReturn' => true,
            ]];
            $edges[] = ['id' => "e-{$previous}-{$id}", 'source' => $previous, 'target' => $id, ...($handle === null ? [] : ['sourceHandle' => $handle])];
            $edges[] = ['id' => "e-{$id}-no", 'source' => $id, 'target' => 'rejected', 'sourceHandle' => 'rejected'];
            $previous = $id;
            $handle = 'approved';
        }

        $x = 240 * (count($steps) + 1);

        if ($update !== null) {
            $nodes[] = ['id' => 'apply', 'type' => 'update_record', 'position' => ['x' => $x, 'y' => 120], 'config' => ['label' => ['en' => 'Apply the change', 'ar' => 'تطبيق التغيير'], 'action' => $update]];
            $edges[] = ['id' => "e-{$previous}-apply", 'source' => $previous, 'target' => 'apply', 'sourceHandle' => 'approved'];
            $previous = 'apply';
            $handle = null;
            $x += 240;
        }

        $nodes[] = ['id' => 'approved', 'type' => 'end', 'position' => ['x' => $x, 'y' => 60], 'config' => ['outcome' => 'approved']];
        $nodes[] = ['id' => 'rejected', 'type' => 'end', 'position' => ['x' => $x, 'y' => 220], 'config' => ['outcome' => 'rejected']];
        $edges[] = ['id' => "e-{$previous}-yes", 'source' => $previous, 'target' => 'approved', ...($handle === null ? [] : ['sourceHandle' => $handle])];

        return ['format' => 'mawred.workflow/v1', 'nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * @return list<array{code: string, name: array<string, string>, description: array<string, string>, category: RequestCategory, icon: string, chain: 'standard'|'hr'|'extended'|'hr_update'|'swap', fields: list<array<string, mixed>>, sensitive?: bool, phase?: int, update?: string, leave?: string, behaviour?: string}>
     */
    public static function catalog(): array
    {
        $today = ['ref' => 'ctx.today'];
        $reason = self::field('reason', 'textarea', 'Reason', 'السبب');
        $hourly = ['op' => 'eq', 'args' => [['ref' => 'field.kind'], 'hourly']];
        $contact = ['op' => 'contains', 'args' => [['ref' => 'field.change'], 'contact']];
        $bank = ['op' => 'contains', 'args' => [['ref' => 'field.change'], 'bank']];
        $oneDay = ['op' => 'all', 'args' => [['op' => 'not', 'args' => [['op' => 'empty', 'args' => [['ref' => 'field.dates.from']]]]], ['op' => 'eq', 'args' => [['ref' => 'field.dates.from'], ['ref' => 'field.dates.to']]]]];
        $halfDay = self::field('half_day', 'radio', 'How much of the day?', 'أي جزء من اليوم؟', ['default' => 'full', 'visible' => $oneDay, 'options' => self::options([
            'full' => ['The whole day', 'اليوم كاملاً'], 'morning' => ['Morning only', 'الصباح فقط'], 'afternoon' => ['Afternoon only', 'بعد الظهر فقط'],
        ])]);

        return [
            [
                'code' => 'DAY_OFF', 'category' => RequestCategory::Time, 'icon' => 'calendar', 'chain' => 'standard', 'behaviour' => 'hourly_permission',
                'name' => ['en' => 'Day off or hourly permission', 'ar' => 'إجازة يوم أو استئذان بالساعات'],
                'description' => ['en' => 'A day away, or a few hours out of a working day.', 'ar' => 'يوم غياب، أو بضع ساعات خلال يوم العمل.'],
                'fields' => [
                    self::field('kind', 'radio', 'What do you need?', 'ماذا تحتاج؟', ['required' => true, 'default' => 'full_day', 'options' => self::options(['full_day' => ['A full day', 'يوم كامل'], 'hourly' => ['A few hours', 'بضع ساعات']])]),
                    self::field('date', 'date', 'Date', 'التاريخ', ['required' => true, 'validation' => ['min' => $today]]),
                    self::field('from_time', 'time', 'From', 'من', ['required' => $hourly, 'visible' => $hourly]),
                    self::field('to_time', 'time', 'To', 'إلى', ['required' => $hourly, 'visible' => $hourly]),
                    $reason,
                ],
            ],
            [
                'code' => 'BUSINESS_TRIP', 'category' => RequestCategory::Time, 'icon' => 'calendar', 'chain' => 'standard',
                'name' => ['en' => 'Business trip', 'ar' => 'رحلة عمل'],
                'description' => ['en' => 'Travel for work, with an optional cash advance.', 'ar' => 'سفر للعمل مع سلفة نقدية اختيارية.'],
                'fields' => [
                    self::field('destination', 'text', 'Destination', 'الوجهة', ['required' => true]),
                    self::field('dates', 'date_range', 'Travel dates', 'تواريخ السفر', ['required' => true, 'validation' => ['min' => $today]]),
                    self::field('purpose', 'textarea', 'Purpose', 'الغرض', ['required' => true]),
                    self::field('advance', 'money', 'Cash advance (optional)', 'سلفة نقدية (اختياري)'),
                ],
            ],
            [
                'code' => 'EXPENSE', 'category' => RequestCategory::Finance, 'icon' => 'reports', 'chain' => 'standard',
                'name' => ['en' => 'Expense reimbursement', 'ar' => 'استرداد مصروفات'],
                'description' => ['en' => 'Get back money you spent for work.', 'ar' => 'استرد ما أنفقته في العمل.'],
                'fields' => [
                    self::field('description', 'text', 'What was it for?', 'لأي غرض؟', ['required' => true]),
                    self::field('spent_on', 'date', 'Date spent', 'تاريخ الصرف', ['required' => true, 'validation' => ['max' => $today]]),
                    self::field('amount', 'money', 'Amount', 'المبلغ', ['required' => true, 'validation' => ['min' => 1]]),
                    self::field('receipts', 'file', 'Receipts', 'الإيصالات', ['required' => true, 'validation' => ['maxFiles' => 5, 'accept' => ['pdf', 'image']]]),
                ],
            ],
            [
                'code' => 'SALARY_CERTIFICATE', 'category' => RequestCategory::Documents, 'icon' => 'certificates', 'chain' => 'hr', 'sensitive' => true,
                'name' => ['en' => 'Salary certificate', 'ar' => 'شهادة راتب'],
                'description' => ['en' => 'An official letter stating your salary.', 'ar' => 'خطاب رسمي يوضح راتبك.'],
                'fields' => [
                    self::field('addressed_to', 'text', 'Addressed to', 'موجّه إلى', ['required' => true, 'placeholder' => ['en' => 'e.g. Al Rajhi Bank', 'ar' => 'مثال: مصرف الراجحي']]),
                    self::field('language', 'radio', 'Language', 'اللغة', ['required' => true, 'default' => 'ar', 'options' => self::options(['ar' => ['Arabic', 'العربية'], 'en' => ['English', 'الإنجليزية']])]),
                    self::field('purpose', 'textarea', 'Purpose', 'الغرض'),
                ],
            ],
            [
                'code' => 'EXPERIENCE_LETTER', 'category' => RequestCategory::Documents, 'icon' => 'certificates', 'chain' => 'hr',
                'name' => ['en' => 'Experience letter', 'ar' => 'شهادة خبرة'],
                'description' => ['en' => 'A letter confirming your role and service.', 'ar' => 'خطاب يثبت وظيفتك ومدة خدمتك.'],
                'fields' => [
                    self::field('addressed_to', 'text', 'Addressed to', 'موجّه إلى', ['required' => true]),
                    self::field('language', 'radio', 'Language', 'اللغة', ['required' => true, 'default' => 'ar', 'options' => self::options(['ar' => ['Arabic', 'العربية'], 'en' => ['English', 'الإنجليزية']])]),
                ],
            ],
            [
                'code' => 'SALARY_ADVANCE', 'category' => RequestCategory::Finance, 'icon' => 'reports', 'chain' => 'extended', 'sensitive' => true,
                'name' => ['en' => 'Salary advance', 'ar' => 'سلفة على الراتب'],
                'description' => ['en' => 'Part of your salary early, repaid over a few months.', 'ar' => 'جزء من راتبك مقدمًا يُسدّد على أشهر.'],
                'fields' => [
                    self::field('amount', 'money', 'Amount', 'المبلغ', ['required' => true, 'validation' => ['min' => 1]]),
                    self::field('months', 'number', 'Repay over (months)', 'السداد على (أشهر)', ['required' => true, 'default' => 3, 'validation' => ['integer' => true, 'min' => 1, 'max' => 12]]),
                    $reason,
                ],
            ],
            [
                'code' => 'RESIGNATION', 'category' => RequestCategory::People, 'icon' => 'people', 'chain' => 'extended', 'sensitive' => true,
                'name' => ['en' => 'Resignation', 'ar' => 'استقالة'],
                'description' => ['en' => 'Tell us you’re leaving and agree your last day.', 'ar' => 'أبلغنا برغبتك في الاستقالة واتفق على آخر يوم عمل.'],
                'fields' => [
                    self::field('last_day', 'date', 'Proposed last day', 'آخر يوم عمل مقترح', ['required' => true, 'validation' => ['min' => $today]]),
                    self::field('reason', 'textarea', 'Reason (optional)', 'السبب (اختياري)'),
                    self::field('handover', 'textarea', 'Handover notes', 'ملاحظات التسليم'),
                ],
            ],
            [
                'code' => 'DETAILS_CHANGE', 'category' => RequestCategory::People, 'icon' => 'people', 'chain' => 'hr_update', 'sensitive' => true, 'update' => 'people.details_change',
                'name' => ['en' => 'Update my contact or bank details', 'ar' => 'تحديث بيانات الاتصال أو البنك'],
                'description' => ['en' => 'HR checks the change, then your record updates.', 'ar' => 'تراجع الموارد البشرية التغيير ثم يُحدَّث سجلك.'],
                'fields' => [
                    self::field('change', 'checkboxes', 'What’s changing?', 'ما الذي تغيّر؟', ['required' => true, 'options' => self::options(['contact' => ['Personal contact', 'بيانات الاتصال الشخصية'], 'bank' => ['Bank details', 'البيانات البنكية']])]),
                    self::field('personal_phone', 'phone', 'Personal phone', 'الهاتف الشخصي', ['visible' => $contact]),
                    self::field('personal_email', 'email', 'Personal email', 'البريد الشخصي', ['visible' => $contact]),
                    self::field('address', 'textarea', 'Address', 'العنوان', ['visible' => $contact]),
                    self::field('emergency_name', 'text', 'Emergency contact', 'جهة الاتصال للطوارئ', ['visible' => $contact]),
                    self::field('emergency_phone', 'phone', 'Emergency phone', 'هاتف الطوارئ', ['visible' => $contact]),
                    self::field('emergency_relation', 'text', 'Relationship', 'صلة القرابة', ['visible' => $contact]),
                    self::field('bank_name', 'text', 'Bank', 'البنك', ['visible' => $bank, 'required' => $bank]),
                    self::field('iban', 'text', 'IBAN', 'رقم الآيبان', ['visible' => $bank, 'required' => $bank, 'validation' => ['maxLength' => 34]]),
                    self::field('account_name', 'text', 'Account holder', 'اسم صاحب الحساب', ['visible' => $bank, 'required' => $bank]),
                ],
            ],
            [
                'code' => 'ASSET_REQUEST', 'category' => RequestCategory::Assets, 'icon' => 'settings', 'chain' => 'standard',
                'name' => ['en' => 'Equipment or access', 'ar' => 'طلب عهدة أو صلاحية'],
                'description' => ['en' => 'A laptop, phone, access card or other work item.', 'ar' => 'حاسب أو هاتف أو بطاقة دخول أو غيرها.'],
                'fields' => [
                    self::field('item', 'select', 'What do you need?', 'ماذا تحتاج؟', ['required' => true, 'options' => self::options(['laptop' => ['Laptop', 'حاسب محمول'], 'phone' => ['Phone', 'هاتف'], 'monitor' => ['Monitor', 'شاشة'], 'access_card' => ['Access card', 'بطاقة دخول'], 'other' => ['Something else', 'شيء آخر']])]),
                    self::field('details', 'textarea', 'Details', 'التفاصيل', ['required' => true]),
                    self::field('needed_by', 'date', 'Needed by', 'مطلوب بحلول', ['validation' => ['min' => $today]]),
                ],
            ],
            [
                'code' => 'ANNUAL_LEAVE', 'category' => RequestCategory::Leave, 'icon' => 'leave', 'chain' => 'standard', 'leave' => 'ANNUAL',
                'name' => ['en' => 'Annual leave', 'ar' => 'إجازة سنوية'],
                'description' => ['en' => 'Time off from your annual balance.', 'ar' => 'إجازة من رصيدك السنوي.'],
                'fields' => [self::field('dates', 'date_range', 'Dates', 'التواريخ', ['required' => true, 'validation' => ['min' => $today]]), $halfDay, self::field('note', 'textarea', 'Note', 'ملاحظة')],
            ],
            [
                'code' => 'SICK_LEAVE', 'category' => RequestCategory::Leave, 'icon' => 'leave', 'chain' => 'standard', 'leave' => 'SICK',
                'name' => ['en' => 'Sick leave', 'ar' => 'إجازة مرضية'],
                'description' => ['en' => 'Time off when you’re unwell, with a medical note.', 'ar' => 'إجازة عند المرض مع تقرير طبي.'],
                'fields' => [
                    self::field('dates', 'date_range', 'Dates', 'التواريخ', ['required' => true]),
                    $halfDay,
                    self::field('medical_note', 'file', 'Medical note', 'التقرير الطبي', ['validation' => ['maxFiles' => 2, 'accept' => ['pdf', 'image']]]),
                ],
            ],
            [
                'code' => 'SHIFT_SWAP', 'category' => RequestCategory::Time, 'icon' => 'schedule', 'chain' => 'swap', 'update' => 'scheduling.shift_swap',
                'name' => ['en' => 'Shift swap', 'ar' => 'تبديل مناوبة'],
                'description' => ['en' => 'Swap a shift with a colleague.', 'ar' => 'بدّل مناوبة مع زميل.'],
                'fields' => [
                    self::field('colleague', 'employee', 'Swap with', 'التبديل مع', ['required' => true]),
                    self::field('date', 'date', 'Shift date', 'تاريخ المناوبة', ['required' => true, 'validation' => ['min' => $today]]),
                    self::field('note', 'textarea', 'Note', 'ملاحظة'),
                ],
            ],
            [
                'code' => 'ATTENDANCE_CORRECTION', 'category' => RequestCategory::Time, 'icon' => 'attendance', 'chain' => 'standard', 'update' => 'attendance.correction',
                'name' => ['en' => 'Attendance correction', 'ar' => 'تصحيح الحضور'],
                'description' => ['en' => 'Fix a missed or wrong check-in or check-out.', 'ar' => 'صحّح تسجيل دخول أو خروج فائت أو خاطئ.'],
                'fields' => [
                    self::field('date', 'date', 'Date', 'التاريخ', ['required' => true, 'validation' => ['max' => $today]]),
                    self::field('check_in', 'time', 'Check-in', 'الدخول'),
                    self::field('check_out', 'time', 'Check-out', 'الخروج'),
                    self::field('reason', 'textarea', 'Reason', 'السبب', ['required' => true]),
                ],
            ],
            [
                'code' => 'OVERTIME', 'category' => RequestCategory::Time, 'icon' => 'attendance', 'chain' => 'standard',
                'name' => ['en' => 'Overtime', 'ar' => 'عمل إضافي'],
                'description' => ['en' => 'Approval for hours beyond your schedule.', 'ar' => 'موافقة على ساعات تتجاوز جدولك.'],
                'fields' => [
                    self::field('date', 'date', 'Date', 'التاريخ', ['required' => true]),
                    self::field('hours', 'number', 'Hours', 'الساعات', ['required' => true, 'validation' => ['min' => 0.25, 'max' => 16]]),
                    self::field('reason', 'textarea', 'Reason', 'السبب', ['required' => true]),
                ],
            ],
            [
                'code' => 'JOB_REQUISITION', 'category' => RequestCategory::People, 'icon' => 'hiring', 'chain' => 'standard', 'update' => 'hiring.requisition',
                'name' => ['en' => 'Job requisition', 'ar' => 'طلب توظيف'],
                'description' => ['en' => 'Ask to hire for a new or vacated position.', 'ar' => 'اطلب التوظيف لوظيفة جديدة أو شاغرة.'],
                'fields' => [
                    self::field('title', 'text', 'Job title', 'المسمى الوظيفي', ['required' => true, 'validation' => ['max' => 120]]),
                    self::field('branch', 'branch', 'Branch', 'الفرع', ['required' => true]),
                    self::field('department', 'department', 'Department', 'القسم'),
                    self::field('headcount', 'number', 'Number of hires', 'عدد الموظفين المطلوبين', ['required' => true, 'default' => 1, 'validation' => ['min' => 1, 'max' => 50, 'integer' => true]]),
                    self::field('reason', 'radio', 'Reason', 'السبب', ['required' => true, 'default' => 'new', 'options' => self::options([
                        'new' => ['New position', 'وظيفة جديدة'],
                        'replacement' => ['Replacement', 'بديل لموظف'],
                    ])]),
                    self::field('replaces', 'employee', 'Replaces', 'بديلًا عن', ['visible' => ['op' => 'eq', 'args' => [['ref' => 'field.reason'], 'replacement']]]),
                    self::field('target_start', 'date', 'Target start date', 'تاريخ المباشرة المستهدف', ['validation' => ['min' => $today]]),
                    self::field('salary_min', 'money', 'Salary from (monthly)', 'الراتب من (شهريًا)'),
                    self::field('salary_max', 'money', 'Salary to (monthly)', 'الراتب إلى (شهريًا)'),
                    self::field('justification', 'textarea', 'Why is this hire needed?', 'لماذا نحتاج هذا التعيين؟', ['required' => true, 'validation' => ['max' => 2000]]),
                ],
            ],
            [
                'code' => 'TRAINING', 'category' => RequestCategory::Training, 'icon' => 'training', 'chain' => 'standard', 'update' => 'training.external',
                'name' => ['en' => 'Training request', 'ar' => 'طلب تدريب'],
                'description' => ['en' => 'A course or certification you’d like to take.', 'ar' => 'دورة أو شهادة ترغب في الحصول عليها.'],
                'fields' => [
                    self::field('course', 'text', 'Course', 'الدورة', ['required' => true]),
                    self::field('provider', 'text', 'Provider', 'الجهة المقدّمة'),
                    self::field('dates', 'date_range', 'Dates', 'التواريخ'),
                    self::field('cost', 'money', 'Cost', 'التكلفة'),
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private static function field(string $key, string $type, string $en, string $ar, array $extra = []): array
    {
        return ['key' => $key, 'type' => $type, 'label' => ['en' => $en, 'ar' => $ar], ...$extra];
    }

    /**
     * @param  array<string, array{0: string, 1: string}>  $options
     * @return list<array{value: string, label: array{en: string, ar: string}}>
     */
    private static function options(array $options): array
    {
        $list = [];

        foreach ($options as $value => [$en, $ar]) {
            $list[] = ['value' => $value, 'label' => ['en' => $en, 'ar' => $ar]];
        }

        return $list;
    }
}
