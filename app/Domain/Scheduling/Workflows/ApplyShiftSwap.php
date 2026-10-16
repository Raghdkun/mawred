<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Workflows;

use App\Domain\People\Models\Employee;
use App\Domain\Scheduling\Actions\SwapShifts;
use App\Domain\Workflows\Engine\RecordUpdate;
use App\Domain\Workflows\Engine\StepContext;
use App\Support\Actors\Actor;
use RuntimeException;

/** Update Record for the Shift swap request (D72): swaps the shifts once everyone has agreed. */
final readonly class ApplyShiftSwap implements RecordUpdate
{
    public function __construct(private SwapShifts $swap) {}

    public function key(): string
    {
        return 'scheduling.shift_swap';
    }

    public function label(): array
    {
        return ['en' => 'Swap the shifts on the roster', 'ar' => 'تبديل المناوبات في الجدول'];
    }

    public function availableInPhase(): int
    {
        return 0;
    }

    public function apply(StepContext $context): void
    {
        $answers = is_array($context->instance->context['field'] ?? null) ? $context->instance->context['field'] : [];
        $requester = $context->instance->requester_employee_id === null ? null : Employee::query()->find($context->instance->requester_employee_id);
        $colleague = is_string($answers['colleague'] ?? null) ? Employee::query()->where('ulid', $answers['colleague'])->first() : null;
        $date = is_string($answers['date'] ?? null) ? $answers['date'] : null;

        if (! $requester instanceof Employee || ! $colleague instanceof Employee || $date === null) {
            throw new RuntimeException('The swap request is missing the colleague or the date.');
        }

        if (! $context->simulating) {
            $this->swap->handle(Actor::system('workflow:'.$context->step->ulid), $requester, $colleague, $date, $context->instance->ulid);
        }
    }
}
