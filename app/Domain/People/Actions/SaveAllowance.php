<?php

declare(strict_types=1);

namespace App\Domain\People\Actions;

use App\Domain\People\Data\AllowanceInputData;
use App\Domain\People\Enums\AllowanceType;
use App\Domain\People\Enums\HistoryEvent;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\EmployeeAllowance;
use App\Domain\People\Support\FieldGuard;
use App\Domain\People\Support\History;
use App\Support\Actors\Actor;
use App\Support\Money\Money;
use Illuminate\Database\ConnectionInterface;

/**
 * Adds or changes an allowance (D116), including ending it with an end date. Each change goes on
 * the employment timeline with the new amount, encrypted like salary.
 */
final readonly class SaveAllowance
{
    public function __construct(private ConnectionInterface $db, private FieldGuard $guard) {}

    public function handle(Actor $actor, Employee $employee, AllowanceInputData $input, ?EmployeeAllowance $allowance = null): EmployeeAllowance
    {
        $this->guard->assertCanWrite($actor, 'employee.salary', 'amount');
        $amount = Money::fromDecimal($input->amount, mb_strtoupper($input->currency));
        $type = AllowanceType::from($input->type);

        return $this->db->transaction(function () use ($actor, $employee, $input, $allowance, $amount, $type): EmployeeAllowance {
            $isNew = ! $allowance instanceof EmployeeAllowance;
            $allowance ??= new EmployeeAllowance(['employee_id' => $employee->id, 'created_by' => $actor->user?->id]);
            $ending = ! $isNew && $allowance->effective_to === null && $input->effectiveTo !== null;

            $allowance->fill([
                'type' => $type,
                'label' => $type === AllowanceType::Other ? $input->label : null,
                'amount' => $amount,
                'effective_from' => $input->effectiveFrom,
                'effective_to' => $input->effectiveTo,
                'note' => $input->note,
            ])->save();

            History::record($actor, $employee, HistoryEvent::AllowanceChanged, $ending ? (string) $input->effectiveTo : $input->effectiveFrom, [
                'allowance' => $allowance->ulid,
                'type' => $type->value,
                'label' => $allowance->label,
                'action' => $isNew ? 'added' : ($ending ? 'ended' : 'changed'),
                'currency' => $amount->currency,
            ], $input->note, $amount);

            return $allowance;
        });
    }
}
