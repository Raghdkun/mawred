<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Forms\Actions\PublishForm;
use App\Domain\Forms\Actions\SaveFormDraft;
use App\Domain\Forms\Actions\SaveFormSettings;
use App\Domain\Forms\Data\FormSettingsData;
use App\Domain\Forms\Models\Form;
use App\Domain\Hiring\Enums\StageKind;
use App\Domain\Hiring\Models\PipelineStage;
use App\Domain\Hiring\Models\ScorecardCriterion;
use App\Domain\Hiring\Support\HiringSettings;
use App\Domain\Setup\Support\Settings;
use App\Domain\Workflows\Actions\PublishWorkflow;
use App\Domain\Workflows\Actions\SaveWorkflowDefinition;
use App\Domain\Workflows\Actions\SaveWorkflowDraft;
use App\Domain\Workflows\Models\WorkflowDefinition;
use App\Support\Actors\Actor;
use Illuminate\Database\ConnectionInterface;

/**
 * Hiring defaults (D81): the default pipeline every requisition copies, the default application
 * form, and starting texts for the careers page. Idempotent; never overwrites HR's changes.
 */
final readonly class SeedHiringDefaults
{
    public function __construct(
        private ConnectionInterface $db,
        private Settings $settings,
        private HiringSettings $hiring,
        private SaveFormSettings $formSettings,
        private SaveFormDraft $formDraft,
        private PublishForm $publishForm,
        private SaveWorkflowDefinition $workflowDefinition,
        private SaveWorkflowDraft $workflowDraft,
        private PublishWorkflow $publishWorkflow,
    ) {}

    public function handle(): void
    {
        $this->db->transaction(function (): void {
            if (! PipelineStage::query()->whereNull('job_requisition_id')->exists()) {
                foreach (self::stages() as $position => [$kind, $en, $ar]) {
                    PipelineStage::query()->create(['job_requisition_id' => null, 'name' => ['en' => $en, 'ar' => $ar], 'kind' => $kind, 'position' => $position]);
                }
            }

            if (! ScorecardCriterion::query()->whereNull('job_requisition_id')->exists()) {
                foreach (self::criteria() as $position => [$en, $ar]) {
                    ScorecardCriterion::query()->create(['job_requisition_id' => null, 'name' => ['en' => $en, 'ar' => $ar], 'position' => $position]);
                }
            }

            if (! $this->hiring->defaultForm() instanceof Form) {
                $actor = Actor::system('seed');
                $form = $this->formSettings->handle($actor, FormSettingsData::from([
                    'code' => HiringSettings::DEFAULT_FORM_CODE,
                    'name' => ['en' => 'Job application', 'ar' => 'طلب وظيفة'],
                    'category' => 'hiring',
                ]));
                $this->formDraft->handle($actor, $form, self::applicationForm());
                $this->publishForm->handle($actor, $form);
            }

            if (! WorkflowDefinition::query()->where('code', SubmitOffer::WORKFLOW)->exists()) {
                $actor = Actor::system('seed');
                $flow = $this->workflowDefinition->handle($actor, SubmitOffer::WORKFLOW, ['en' => 'Job offer', 'ar' => 'عرض وظيفي'], [], 'hiring');
                $this->workflowDraft->handle($actor, $flow, self::offerWorkflow());
                $this->publishWorkflow->handle($actor, $flow, ['hiring_manager', 'candidate', 'job', 'start_on', 'salary']);
            }

            if (! $this->settings->has('hiring.careers_intro')) {
                $this->settings->set('hiring.careers_intro', [
                    'en' => 'Join our team. Browse open roles and apply in a few minutes.',
                    'ar' => 'انضم إلى فريقنا. تصفّح الوظائف المتاحة وقدّم خلال دقائق.',
                ]);
            }

            if (! $this->settings->has('hiring.privacy_notice')) {
                $this->settings->set('hiring.privacy_notice', [
                    'en' => 'We use your details only to consider you for this and similar roles, and keep them for 12 months after your last application unless you join our talent pool.',
                    'ar' => 'نستخدم بياناتك فقط للنظر في طلبك لهذه الوظيفة والوظائف المشابهة، ونحتفظ بها 12 شهرًا بعد آخر طلب ما لم تنضم إلى قائمة المواهب لدينا.',
                ]);
            }
        });
    }

    /**
     * @return list<array{0: StageKind, 1: string, 2: string}>
     */
    public static function stages(): array
    {
        return [
            [StageKind::Applied, 'Applied', 'تقدّم'],
            [StageKind::Screening, 'Screening', 'الفرز'],
            [StageKind::Interview, 'Interview', 'المقابلة'],
            [StageKind::Offer, 'Offer', 'العرض الوظيفي'],
            [StageKind::Hired, 'Hired', 'تم التعيين'],
        ];
    }

    /**
     * Job offer approval (D91): the requisition's hiring manager, then HR. Nobody resolving falls
     * back to the workflow fallback role.
     *
     * @return array<string, mixed>
     */
    public static function offerWorkflow(): array
    {
        $sla = ['hours' => 48, 'reminderHours' => 24, 'escalateTo' => ['type' => 'assignee_manager']];
        $step = fn (string $id, int $x, array $label, array $assignee): array => ['id' => $id, 'type' => 'approval', 'position' => ['x' => $x, 'y' => 120], 'config' => [
            'label' => $label, 'assignee' => $assignee, 'mode' => 'any', 'sla' => $sla, 'allowReturn' => false,
        ]];

        return ['format' => 'mawred.workflow/v1', 'nodes' => [
            ['id' => 'start', 'type' => 'start', 'config' => [], 'position' => ['x' => 0, 'y' => 120]],
            $step('manager', 240, ['en' => 'Hiring manager approval', 'ar' => 'موافقة مدير التوظيف'], ['type' => 'field', 'field' => 'hiring_manager']),
            $step('hr', 480, ['en' => 'HR approval', 'ar' => 'موافقة الموارد البشرية'], ['type' => 'role', 'role' => 'hr_admin', 'scoped' => false]),
            ['id' => 'approved', 'type' => 'end', 'position' => ['x' => 720, 'y' => 60], 'config' => ['outcome' => 'approved']],
            ['id' => 'rejected', 'type' => 'end', 'position' => ['x' => 720, 'y' => 220], 'config' => ['outcome' => 'rejected']],
        ], 'edges' => [
            ['id' => 'e-start-manager', 'source' => 'start', 'target' => 'manager'],
            ['id' => 'e-manager-hr', 'source' => 'manager', 'target' => 'hr', 'sourceHandle' => 'approved'],
            ['id' => 'e-manager-no', 'source' => 'manager', 'target' => 'rejected', 'sourceHandle' => 'rejected'],
            ['id' => 'e-hr-yes', 'source' => 'hr', 'target' => 'approved', 'sourceHandle' => 'approved'],
            ['id' => 'e-hr-no', 'source' => 'hr', 'target' => 'rejected', 'sourceHandle' => 'rejected'],
        ]];
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function criteria(): array
    {
        return [
            ['Relevant experience', 'الخبرة ذات الصلة'],
            ['Communication', 'التواصل'],
            ['Problem solving', 'حل المشكلات'],
            ['Teamwork', 'العمل الجماعي'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function applicationForm(): array
    {
        $field = fn (string $key, string $type, string $en, string $ar, array $extra = []): array => ['key' => $key, 'type' => $type, 'label' => ['en' => $en, 'ar' => $ar], ...$extra];

        return ['format' => 'mawred.form/v1', 'pages' => [[
            'key' => 'main', 'title' => ['en' => 'Your application', 'ar' => 'طلبك'], 'sections' => [
                ['key' => 'about', 'title' => ['en' => 'About you', 'ar' => 'معلوماتك'], 'fields' => [
                    $field('full_name', 'text', 'Full name', 'الاسم الكامل', ['required' => true, 'validation' => ['max' => 160]]),
                    $field('email', 'email', 'Email', 'البريد الإلكتروني', ['required' => true]),
                    $field('phone', 'phone', 'Mobile number', 'رقم الجوال', ['required' => true]),
                    $field('location', 'text', 'City', 'المدينة', ['validation' => ['max' => 120]]),
                ]],
                ['key' => 'screening', 'title' => ['en' => 'A few questions', 'ar' => 'بعض الأسئلة'], 'fields' => [
                    $field('right_to_work', 'radio', 'Do you have the right to work in this country?', 'هل يحق لك العمل في هذا البلد؟', ['required' => true, 'options' => [
                        ['value' => 'yes', 'label' => ['en' => 'Yes', 'ar' => 'نعم']],
                        ['value' => 'sponsorship', 'label' => ['en' => 'I need sponsorship', 'ar' => 'أحتاج إلى كفالة']],
                    ]]),
                    $field('experience_years', 'number', 'Years of relevant experience', 'سنوات الخبرة ذات الصلة', ['validation' => ['min' => 0, 'max' => 60, 'integer' => true]]),
                ]],
                ['key' => 'experience', 'title' => ['en' => 'Your experience', 'ar' => 'خبراتك'], 'fields' => [
                    $field('cv', 'file', 'CV', 'السيرة الذاتية', ['required' => true, 'validation' => ['maxFiles' => 1, 'accept' => ['pdf', 'word']]]),
                    $field('linkedin', 'text', 'LinkedIn or portfolio link', 'رابط LinkedIn أو معرض الأعمال', ['validation' => ['max' => 300]]),
                    $field('cover_note', 'textarea', 'Why this role?', 'لماذا هذه الوظيفة؟', ['validation' => ['max' => 3000]]),
                ]],
            ],
        ]]];
    }
}
