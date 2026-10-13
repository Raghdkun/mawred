<?php

declare(strict_types=1);

namespace App\Domain\People\Support;

use App\Domain\People\Enums\HistoryEvent;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\EmploymentHistoryEntry;
use App\Support\Actors\Actor;
use App\Support\Money\Money;

/**
 * Appends to the employment timeline. `changes` holds public ids and plain values only; the
 * salary amount goes in its encrypted column.
 */
final class History
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public static function record(Actor $actor, Employee $employee, HistoryEvent $event, string $effectiveOn, array $changes = [], ?string $note = null, ?Money $salary = null): EmploymentHistoryEntry
    {
        return EmploymentHistoryEntry::query()->create([
            'employee_id' => $employee->id,
            'event' => $event,
            'effective_on' => $effectiveOn,
            'changes' => $changes,
            'salary' => $salary,
            'note' => $note,
            'recorded_by' => $actor->user?->id,
        ]);
    }
}
