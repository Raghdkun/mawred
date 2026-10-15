<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Integrations\Enums\ReceiptStatus;
use App\Domain\Integrations\Models\CognitoReceipt;
use App\Domain\People\Models\Employee;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

/** An admin names the employee for an entry nobody matched (D61); their request then starts. */
final readonly class AssignCognitoReceipt
{
    public function __construct(private ProcessCognitoReceipt $process) {}

    public function handle(Actor $actor, CognitoReceipt $receipt, Employee $employee): CognitoReceipt
    {
        if ($receipt->status !== ReceiptStatus::NeedsPerson) {
            throw new DomainRuleViolation('integrations.receipt_not_waiting', __('This entry is not waiting for a person.'));
        }

        activity('integrations')->causedBy($actor->user)->event('cognito.assigned')->withProperties(['receipt' => $receipt->ulid, 'employee' => $employee->ulid])->log('Cognito entry assigned');

        return $this->process->handle($receipt->forceFill(['employee_id' => $employee->id]), $employee);
    }
}
