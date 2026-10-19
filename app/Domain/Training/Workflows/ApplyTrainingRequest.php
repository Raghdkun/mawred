<?php

declare(strict_types=1);

namespace App\Domain\Training\Workflows;

use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Training\Actions\RecordExternalTraining;
use App\Domain\Workflows\Engine\RecordUpdate;
use App\Domain\Workflows\Engine\StepContext;
use App\Support\Actors\Actor;
use RuntimeException;

/** Update Record for the Training request (D105): puts the approved course on the training record. */
final readonly class ApplyTrainingRequest implements RecordUpdate
{
    public function __construct(private RecordExternalTraining $record) {}

    public function key(): string
    {
        return 'training.external';
    }

    public function label(): array
    {
        return ['en' => 'Add to the training record', 'ar' => 'الإضافة إلى السجل التدريبي'];
    }

    public function availableInPhase(): int
    {
        return 0;
    }

    public function apply(StepContext $context): void
    {
        $instance = $context->instance;
        $request = $instance->subject_type === EmployeeRequest::class && $instance->subject_id !== null
            ? EmployeeRequest::query()->find($instance->subject_id)
            : null;

        if (! $request instanceof EmployeeRequest) {
            throw new RuntimeException('Only a Training request can add to the training record.');
        }

        if (! $context->simulating) {
            $submission = $request->submission()->first();
            $answers = $submission instanceof FormSubmission ? $submission->data : [];
            $this->record->handle(Actor::system('workflow:'.$context->step->ulid), $request, $answers);
        }
    }
}
