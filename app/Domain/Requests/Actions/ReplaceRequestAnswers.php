<?php

declare(strict_types=1);

namespace App\Domain\Requests\Actions;

use App\Domain\Forms\Actions\SubmitForm;
use App\Domain\Forms\Enums\SubmissionSource;
use App\Domain\Forms\Models\Form;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Enums\RequestStatus;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Workflows\Engine\Timeline;
use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Models\WorkflowInstance;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/**
 * An outside system changed the answers of an open request (a Cognito "update" event, ADR-0015).
 * In review: the answers are replaced where they stand. Returned: the request goes back into
 * review, like a resubmission. Closed requests can't change (the caller logs a late update).
 */
final readonly class ReplaceRequestAnswers
{
    public function __construct(private ConnectionInterface $db, private SubmitForm $submitForm, private WorkflowEngine $engine) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public function handle(Actor $actor, EmployeeRequest $request, array $values, string $session): EmployeeRequest
    {
        if (! $request->status->isOpen()) {
            throw new DomainRuleViolation('requests.closed', __('This request has already been decided.'));
        }

        $instance = $request->instance()->first();

        return $this->db->transaction(function () use ($actor, $request, $values, $session, $instance): EmployeeRequest {
            $previous = $request->submission()->firstOrFail();
            $version = $previous->pinnedVersion();
            $form = Form::withTrashed()->findOrFail($version->form_id);
            $employee = Employee::withTrashed()->find($request->requester_employee_id);
            $submission = $this->submitForm->handle($actor, $form, $version, $values, SubmissionSource::Cognito, $session, replaces: $previous, for: $employee);

            $returned = $request->status === RequestStatus::Returned;
            $request->forceFill(['form_submission_id' => $submission->id, 'status' => RequestStatus::InReview])->save();

            if ($instance instanceof WorkflowInstance) {
                if ($returned) {
                    $this->engine->resubmit($instance, $submission->data, null);
                } else {
                    $instance->forceFill(['context' => [...$instance->context, 'field' => $submission->data]])->save();
                    Timeline::add($instance, 'answers.updated', null, null, ['via' => $actor->channel->value]);
                }
            }

            activity('requests')->performedOn($request)->event('answers.replaced')->withProperties(['channel' => $actor->channel->value])->log('Request answers updated');

            return $request;
        });
    }
}
