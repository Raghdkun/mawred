<?php

declare(strict_types=1);

namespace App\Domain\Training\Actions;

use App\Domain\Workflows\Actions\PublishWorkflow;
use App\Domain\Workflows\Actions\SaveWorkflowDefinition;
use App\Domain\Workflows\Actions\SaveWorkflowDraft;
use App\Domain\Workflows\Models\WorkflowDefinition;
use App\Support\Actors\Actor;
use Illuminate\Database\ConnectionInterface;

/**
 * Training defaults (idempotent): the enrolment approval workflow, the employee's manager with
 * HR as the fallback (D99). It stays editable in the workflow designer.
 */
final readonly class SeedTrainingDefaults
{
    public function __construct(
        private ConnectionInterface $db,
        private SaveWorkflowDefinition $definition,
        private SaveWorkflowDraft $draft,
        private PublishWorkflow $publish,
    ) {}

    public function handle(): void
    {
        $this->db->transaction(function (): void {
            if (! WorkflowDefinition::query()->where('code', Enrol::WORKFLOW)->exists()) {
                $actor = Actor::system('seed');
                $flow = $this->definition->handle($actor, Enrol::WORKFLOW, ['en' => 'Course enrolment', 'ar' => 'التسجيل في دورة'], [], 'training');
                $this->draft->handle($actor, $flow, self::enrolmentWorkflow());
                $this->publish->handle($actor, $flow, ['course', 'due_on']);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function enrolmentWorkflow(): array
    {
        return ['format' => 'mawred.workflow/v1', 'nodes' => [
            ['id' => 'start', 'type' => 'start', 'config' => [], 'position' => ['x' => 0, 'y' => 120]],
            ['id' => 'manager', 'type' => 'approval', 'position' => ['x' => 240, 'y' => 120], 'config' => [
                'label' => ['en' => 'Manager approval', 'ar' => 'موافقة المدير'],
                'assignee' => ['type' => 'manager'],
                'mode' => 'any',
                'sla' => ['hours' => 48, 'reminderHours' => 24, 'escalateTo' => ['type' => 'assignee_manager']],
                'allowReturn' => false,
            ]],
            ['id' => 'approved', 'type' => 'end', 'position' => ['x' => 480, 'y' => 60], 'config' => ['outcome' => 'approved']],
            ['id' => 'rejected', 'type' => 'end', 'position' => ['x' => 480, 'y' => 220], 'config' => ['outcome' => 'rejected']],
        ], 'edges' => [
            ['id' => 'e-start-manager', 'source' => 'start', 'target' => 'manager'],
            ['id' => 'e-manager-yes', 'source' => 'manager', 'target' => 'approved', 'sourceHandle' => 'approved'],
            ['id' => 'e-manager-no', 'source' => 'manager', 'target' => 'rejected', 'sourceHandle' => 'rejected'],
        ]];
    }
}
