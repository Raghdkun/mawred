<?php

declare(strict_types=1);

namespace App\Domain\Requests\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Actions\SubmitForm;
use App\Domain\Forms\Enums\SubmissionSource;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormVersion;
use App\Domain\Forms\Support\FormContext;
use App\Domain\Leave\Actions\PlaceLeaveHold;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Enums\RequestStatus;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Requests\Models\RequestType;
use App\Domain\Requests\Support\RequestNumbers;
use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Models\WorkflowDefinition;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/**
 * Submits a request (handoff 11): validates the answers against the type's published form
 * version, records them, holds any leave it asks for (D64), and starts the type's workflow with
 * the request as its subject.
 */
final readonly class SubmitRequest
{
    public function __construct(
        private ConnectionInterface $db,
        private SubmitForm $submitForm,
        private FormContext $formContext,
        private WorkflowEngine $engine,
        private RequestNumbers $numbers,
        private PlaceLeaveHold $leaveHold,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     * @param  Employee|null  $for  the requester when an integration submits for them (Cognito, D60)
     * @param  array{source_id: int, entry_id: string|null, session: string}|null  $external  where the entry came from
     */
    public function handle(Actor $actor, RequestType $type, array $values, ?Employee $for = null, ?array $external = null): EmployeeRequest
    {
        $employee = $for ?? ($actor->user instanceof User ? Employee::query()->where('user_id', $actor->user->id)->first() : null);
        $user = $for instanceof Employee ? User::query()->find($for->user_id) : $actor->user;

        if (! $employee instanceof Employee || (! $for instanceof Employee && ! $user instanceof User)) {
            throw new DomainRuleViolation('requests.no_employee', __('Only people with an employee record can make requests.'));
        }

        if (! $type->isAvailable()) {
            throw new DomainRuleViolation('requests.unavailable', __('This request type is not available yet.'));
        }

        $form = $type->form()->first();
        $version = $form instanceof Form ? $form->publishedVersion()->first() : null;
        $workflow = $type->workflow()->first();

        if (! $version instanceof FormVersion || ! $workflow instanceof WorkflowDefinition || $workflow->published_version_id === null) {
            throw new DomainRuleViolation('requests.not_ready', __('This request type is not ready. Ask HR to publish its form and workflow.'));
        }

        return $this->db->transaction(function () use ($actor, $user, $employee, $type, $values, $form, $version, $workflow, $for, $external): EmployeeRequest {
            $submission = $external === null
                ? $this->submitForm->handle($actor, $form, $version, $values, SubmissionSource::Internal, for: $for)
                : $this->submitForm->handle($actor, $form, $version, $values, SubmissionSource::Cognito, $external['session'], for: $for);

            $request = new EmployeeRequest([
                'number' => $this->numbers->next(),
                'request_type_id' => $type->id,
                'requester_employee_id' => $employee->id,
                'requester_user_id' => $user?->id,
                'branch_id' => $employee->current_branch_id,
                'department_id' => $employee->current_department_id,
                'form_submission_id' => $submission->id,
                'status' => RequestStatus::InReview,
                'submitted_at' => now(),
                'cognito_source_id' => $external['source_id'] ?? null,
                'external_entry_id' => $external['entry_id'] ?? null,
            ]);
            $request->save();

            if ($type->behaviour !== null) {
                $this->leaveHold->handle($request, $type, $employee, $submission);
            }

            $context = $this->formContext->forEmployee($employee);
            $instance = $this->engine->start($workflow, [
                'field' => $submission->data,
                'requester' => $context['requester'],
                'ctx' => $context['ctx'],
                'request' => ['id' => $request->ulid, 'number' => $request->number, 'type' => $type->code],
            ], $user, $employee, $request);

            $request->forceFill(['workflow_instance_id' => $instance->id])->save();

            activity('requests')->causedBy($actor->user)->performedOn($request)->event('submitted')
                ->withProperties(['number' => $request->number, 'type' => $type->code, 'channel' => $actor->channel->value])->log('Request submitted');

            return $request;
        });
    }
}
