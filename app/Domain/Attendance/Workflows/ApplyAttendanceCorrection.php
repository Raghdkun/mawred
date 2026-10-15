<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Workflows;

use App\Domain\Attendance\Actions\CorrectAttendance;
use App\Domain\People\Models\Employee;
use App\Domain\Workflows\Engine\RecordUpdate;
use App\Domain\Workflows\Engine\StepContext;
use App\Support\Actors\Actor;
use RuntimeException;

/** Update Record for the Attendance correction request: applies the corrected times. */
final readonly class ApplyAttendanceCorrection implements RecordUpdate
{
    public function __construct(private CorrectAttendance $correct) {}

    public function key(): string
    {
        return 'attendance.correction';
    }

    public function label(): array
    {
        return ['en' => 'Apply the corrected check-in and check-out', 'ar' => 'تطبيق أوقات الدخول والخروج المصحّحة'];
    }

    public function availableInPhase(): int
    {
        return 0;
    }

    public function apply(StepContext $context): void
    {
        $answers = is_array($context->instance->context['field'] ?? null) ? $context->instance->context['field'] : [];
        $employee = $context->instance->requester_employee_id === null ? null : Employee::query()->find($context->instance->requester_employee_id);
        $date = is_string($answers['date'] ?? null) ? $answers['date'] : null;

        if (! $employee instanceof Employee || $date === null) {
            throw new RuntimeException('The correction request is missing the person or the date.');
        }

        $this->correct->handle(
            Actor::system('workflow:'.$context->step->ulid),
            $employee,
            $date,
            is_string($answers['check_in'] ?? null) && $answers['check_in'] !== '' ? $answers['check_in'] : null,
            is_string($answers['check_out'] ?? null) && $answers['check_out'] !== '' ? $answers['check_out'] : null,
            is_string($answers['reason'] ?? null) ? $answers['reason'] : 'Approved request',
        );
    }
}
