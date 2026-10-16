<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Workflows;

use App\Domain\Hiring\Actions\OpenRequisition;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Workflows\Engine\RecordUpdate;
use App\Domain\Workflows\Engine\StepContext;
use App\Support\Actors\Actor;
use RuntimeException;

/** Update Record for the Job requisition request (D84): opens the approved requisition. */
final readonly class OpenApprovedRequisition implements RecordUpdate
{
    public function __construct(private OpenRequisition $open) {}

    public function key(): string
    {
        return 'hiring.requisition';
    }

    public function label(): array
    {
        return ['en' => 'Open the job requisition', 'ar' => 'فتح طلب التوظيف'];
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
            throw new RuntimeException('Only a Job requisition request can open a requisition.');
        }

        if (! $context->simulating) {
            $this->open->handle(Actor::system('workflow:'.$context->step->ulid), $request);
        }
    }
}
