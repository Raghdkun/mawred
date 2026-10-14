<?php

declare(strict_types=1);

namespace App\Domain\Requests\Actions;

use App\Domain\Forms\Actions\SubmitForm;
use App\Domain\Forms\Enums\SubmissionSource;
use App\Domain\Forms\Models\Form;
use App\Domain\Requests\Enums\RequestStatus;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Models\WorkflowInstance;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/**
 * After "return for changes": the requester sends corrected answers (validated against the same
 * form version) and the workflow re-enters the step that returned it.
 */
final readonly class ResubmitRequest
{
    public function __construct(private ConnectionInterface $db, private SubmitForm $submitForm, private WorkflowEngine $engine) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public function handle(Actor $actor, EmployeeRequest $request, array $values): EmployeeRequest
    {
        $instance = $request->instance()->first();

        if ($request->status !== RequestStatus::Returned || ! $instance instanceof WorkflowInstance || $actor->user?->id !== $request->requester_user_id) {
            throw new DomainRuleViolation('requests.not_returned', __('This request is not waiting for changes.'));
        }

        return $this->db->transaction(function () use ($actor, $request, $values, $instance): EmployeeRequest {
            $previous = $request->submission()->firstOrFail();
            $version = $previous->pinnedVersion();
            $form = Form::withTrashed()->findOrFail($version->form_id);
            $submission = $this->submitForm->handle($actor, $form, $version, $values, SubmissionSource::Internal);

            $request->forceFill(['form_submission_id' => $submission->id, 'status' => RequestStatus::InReview])->save();
            $this->engine->resubmit($instance, $submission->data, $actor->user);

            activity('requests')->causedBy($actor->user)->performedOn($request)->event('resubmitted')->log('Request resubmitted');

            return $request;
        });
    }
}
