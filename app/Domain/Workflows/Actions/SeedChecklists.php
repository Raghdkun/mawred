<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Actions;

use App\Domain\Workflows\Models\WorkflowDefinition;
use App\Support\Actors\Actor;

/**
 * Onboarding and offboarding checklists (roadmap 3b): parallel to-dos for HR and the manager,
 * started when someone is hired or leaves. Ordinary workflows, so HR can edit them. Idempotent.
 */
final readonly class SeedChecklists
{
    public const string ONBOARDING = 'ONBOARDING';

    public const string OFFBOARDING = 'OFFBOARDING';

    public function __construct(
        private SaveWorkflowDefinition $definition,
        private SaveWorkflowDraft $draft,
        private PublishWorkflow $publish,
    ) {}

    public function handle(): void
    {
        $hr = ['type' => 'role', 'role' => 'hr_officer', 'scoped' => true];
        $manager = ['type' => 'manager'];

        $this->seed(self::ONBOARDING, ['en' => 'Onboarding checklist', 'ar' => 'قائمة استقبال الموظف الجديد'], [
            ['it', ['en' => 'Prepare laptop and accounts', 'ar' => 'تجهيز الحاسب والحسابات'], ['en' => 'Laptop, email, system access and an access card ready for day one.', 'ar' => 'الحاسب والبريد وصلاحيات الأنظمة وبطاقة الدخول جاهزة لليوم الأول.'], $hr],
            ['documents', ['en' => 'Collect documents and sign the contract', 'ar' => 'استلام المستندات وتوقيع العقد'], ['en' => 'ID, certificates, bank details and the signed contract.', 'ar' => 'الهوية والشهادات والبيانات البنكية والعقد الموقّع.'], $hr],
            ['welcome', ['en' => 'Plan the first week', 'ar' => 'خطة الأسبوع الأول'], ['en' => 'Introductions, a buddy and first tasks.', 'ar' => 'التعارف وزميل مرافق والمهام الأولى.'], $manager],
        ]);

        $this->seed(self::OFFBOARDING, ['en' => 'Offboarding checklist', 'ar' => 'قائمة إنهاء الخدمة'], [
            ['handover', ['en' => 'Agree the handover', 'ar' => 'الاتفاق على التسليم'], ['en' => 'Who takes over each responsibility, and when.', 'ar' => 'من يتسلّم كل مسؤولية ومتى.'], $manager],
            ['assets', ['en' => 'Collect assets and close accounts', 'ar' => 'استلام العُهد وإغلاق الحسابات'], ['en' => 'Laptop, phone, access card; disable system access on the last day.', 'ar' => 'الحاسب والهاتف وبطاقة الدخول، وإيقاف الصلاحيات في آخر يوم.'], $hr],
            ['settlement', ['en' => 'Final settlement and exit interview', 'ar' => 'المخالصة النهائية ومقابلة الخروج'], ['en' => 'End-of-service calculation, experience letter and exit interview.', 'ar' => 'حساب نهاية الخدمة وشهادة الخبرة ومقابلة الخروج.'], $hr],
        ]);
    }

    /**
     * @param  array<string, string>  $name
     * @param  list<array{0: string, 1: array<string, string>, 2: array<string, string>, 3: array<string, mixed>}>  $tasks
     */
    private function seed(string $code, array $name, array $tasks): void
    {
        if (WorkflowDefinition::withTrashed()->where('code', $code)->exists()) {
            return;
        }

        $actor = Actor::system('seed');
        $nodes = [
            ['id' => 'start', 'type' => 'start', 'config' => [], 'position' => ['x' => 0, 'y' => 200]],
            ['id' => 'split', 'type' => 'split', 'config' => [], 'position' => ['x' => 240, 'y' => 200]],
            ['id' => 'join', 'type' => 'join', 'config' => [], 'position' => ['x' => 760, 'y' => 200]],
            ['id' => 'done', 'type' => 'end', 'config' => ['outcome' => 'completed'], 'position' => ['x' => 1020, 'y' => 200]],
        ];
        $edges = [['id' => 'e-start', 'source' => 'start', 'target' => 'split'], ['id' => 'e-done', 'source' => 'join', 'target' => 'done']];

        foreach ($tasks as $i => [$id, $label, $instructions, $assignee]) {
            $nodes[] = ['id' => $id, 'type' => 'task', 'position' => ['x' => 500, 'y' => 60 + $i * 140], 'config' => [
                'label' => $label, 'instructions' => $instructions, 'assignee' => $assignee, 'sla' => ['hours' => 72, 'reminderHours' => 48],
            ]];
            $edges[] = ['id' => "e-split-{$id}", 'source' => 'split', 'target' => $id];
            $edges[] = ['id' => "e-{$id}-join", 'source' => $id, 'target' => 'join', 'sourceHandle' => 'done'];
        }

        $definition = $this->definition->handle($actor, $code, $name, [], 'people');
        $this->draft->handle($actor, $definition, ['format' => 'mawred.workflow/v1', 'nodes' => $nodes, 'edges' => $edges]);
        $this->publish->handle($actor, $definition, []);
    }
}
