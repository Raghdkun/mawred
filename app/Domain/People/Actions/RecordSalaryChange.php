<?php

declare(strict_types=1);

namespace App\Domain\People\Actions;

use App\Domain\People\Enums\HistoryEvent;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\EmploymentHistoryEntry;
use App\Domain\People\Support\FieldGuard;
use App\Domain\People\Support\History;
use App\Support\Actors\Actor;
use App\Support\Money\Money;

/**
 * Salary is history, not a field: each change is an encrypted row with its effective date.
 * Mawred exports salary data but does not run payroll (D9).
 */
final readonly class RecordSalaryChange
{
    public function __construct(private FieldGuard $guard) {}

    public function handle(Actor $actor, Employee $employee, Money $salary, string $effectiveOn, ?string $note = null): EmploymentHistoryEntry
    {
        $this->guard->assertCanWrite($actor, 'employee.salary', 'salary');

        return History::record($actor, $employee, HistoryEvent::SalaryChanged, $effectiveOn, ['currency' => $salary->currency], $note, $salary);
    }
}
