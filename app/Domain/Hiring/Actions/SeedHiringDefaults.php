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
use App\Domain\Hiring\Support\HiringSettings;
use App\Domain\Setup\Support\Settings;
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
    ) {}

    public function handle(): void
    {
        $this->db->transaction(function (): void {
            if (! PipelineStage::query()->whereNull('job_requisition_id')->exists()) {
                foreach (self::stages() as $position => [$kind, $en, $ar]) {
                    PipelineStage::query()->create(['job_requisition_id' => null, 'name' => ['en' => $en, 'ar' => $ar], 'kind' => $kind, 'position' => $position]);
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
