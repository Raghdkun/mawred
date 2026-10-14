<?php

declare(strict_types=1);

namespace App\Domain\Requests\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Actions\SubmitForm;
use App\Domain\Forms\Enums\SubmissionSource;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormVersion;
use App\Domain\Forms\Support\FormContext;
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
 * version, records them, and starts the type's workflow with the request as its subject.
 */
final readonly class SubmitRequest
{
    public function __construct(
        private ConnectionInterface $db,
        private SubmitForm $submitForm,
        private FormContext $formContext,
        private WorkflowEngine $engine,
        private RequestNumbers $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public function handle(Actor $actor, RequestType $type, array $values): EmployeeRequest
    {
        $user = $actor->user;
        $employee = $user instanceof User ? Employee::query()->where('user_id', $user->id)->first() : null;

        if (! $user instanceof User || ! $employee instanceof Employee) {
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

        return $this->db->transaction(function () use ($actor, $user, $employee, $type, $values, $form, $version, $workflow): EmployeeRequest {
            $submission = $this->submitForm->handle($actor, $form, $version, $values, SubmissionSource::Internal);

            $request = new EmployeeRequest([
                'number' => $this->numbers->next(),
                'request_type_id' => $type->id,
                'requester_employee_id' => $employee->id,
                'requester_user_id' => $user->id,
                'branch_id' => $employee->current_branch_id,
                'department_id' => $employee->current_department_id,
                'form_submission_id' => $submission->id,
                'status' => RequestStatus::InReview,
                'submitted_at' => now(),
            ]);
            $request->save();

            $context = $this->formContext->forEmployee($employee);
            $instance = $this->engine->start($workflow, [
                'field' => $submission->data,
                'requester' => $context['requester'],
                'ctx' => $context['ctx'],
                'request' => ['id' => $request->ulid, 'number' => $request->number, 'type' => $type->code],
            ], $user, $employee, $request);

            $request->forceFill(['workflow_instance_id' => $instance->id])->save();

            activity('requests')->causedBy($user)->performedOn($request)->event('submitted')
                ->withProperties(['number' => $request->number, 'type' => $type->code])->log('Request submitted');

            return $request;
        });
    }
}
