<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Hiring\Enums\RequisitionStatus;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Support\RequisitionAnswers;
use App\Domain\Hiring\Support\RequisitionNumbers;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Actions\SubmitRequest;
use App\Domain\Requests\Models\RequestType;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/**
 * A manager asks to hire (D84): the answers go through the Job requisition request type, so the
 * approval chain runs in the workflow engine and lands in approvers' inboxes. The requisition
 * waits as pending until the chain approves it.
 */
final readonly class RaiseRequisition
{
    public function __construct(
        private ConnectionInterface $db,
        private SubmitRequest $submit,
        private RequisitionNumbers $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public function handle(Actor $actor, array $values): JobRequisition
    {
        $type = RequestType::query()->where('code', JobRequisition::REQUEST_TYPE)->where('is_active', true)->first();

        if (! $type instanceof RequestType) {
            throw new DomainRuleViolation('hiring.no_requisition_type', __('The Job requisition request type is turned off. Ask HR to turn it on.'));
        }

        return $this->db->transaction(function () use ($actor, $type, $values): JobRequisition {
            $request = $this->submit->handle($actor, $type, $values);
            $requester = Employee::query()->find($request->requester_employee_id);
            $submission = $request->submission()->first();
            $answers = $submission instanceof FormSubmission ? $submission->data : null;

            $requisition = new JobRequisition([
                ...RequisitionAnswers::attributes(is_array($answers) ? $answers : $values, $requester, $request->number),
                'number' => $this->numbers->next(),
                'requested_by_user_id' => $actor->user?->id,
                'request_id' => $request->id,
                'status' => RequisitionStatus::Pending,
            ]);
            $requisition->save();

            activity('hiring')->causedBy($actor->user)->performedOn($requisition)->event('raised')
                ->withProperties(['number' => $requisition->number, 'request' => $request->number])->log('Requisition raised');

            return $requisition;
        });
    }
}
