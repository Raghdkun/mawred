<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormVersion;
use App\Domain\Forms\Support\FormSchema;
use App\Domain\Integrations\Cognito\CognitoFiles;
use App\Domain\Integrations\Cognito\CognitoMapping;
use App\Domain\Integrations\Contracts\CognitoApplications;
use App\Domain\Integrations\Enums\ReceiptStatus;
use App\Domain\Integrations\Models\CognitoReceipt;
use App\Domain\Integrations\Models\CognitoSource;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Actions\ReplaceRequestAnswers;
use App\Domain\Requests\Actions\SubmitRequest;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Requests\Models\RequestType;
use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Models\WorkflowInstance;
use App\Support\Actors\Actor;
use App\Support\Actors\Channel;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Turns a stored Cognito payload into Mawred state (ADR-0015, D60, D61):
 * - submit: find the employee, map the answers, and submit the request; unknown people wait
 *   for an admin ("needs a person");
 * - update: replace the answers while the request is open, otherwise log a late update;
 * - delete: cancel the request while it is still running.
 * A source linked to a job posting creates (or refreshes) the candidate's application instead.
 * Capture mode stores the payload as the source's sample instead.
 */
final readonly class ProcessCognitoReceipt
{
    public function __construct(
        private SubmitRequest $submit,
        private ReplaceRequestAnswers $replace,
        private WorkflowEngine $engine,
        private CognitoFiles $files,
        private CognitoApplications $applications,
    ) {}

    public function handle(CognitoReceipt $receipt, ?Employee $person = null): CognitoReceipt
    {
        $source = $receipt->source()->firstOrFail();
        $receipt->forceFill(['attempts' => $receipt->attempts + 1])->save();

        if ($source->capture_sample) {
            $source->forceFill(['capture_sample' => false, 'sample_payload' => $receipt->payload, 'sample_captured_at' => now()])->save();

            return $this->finish($receipt, ReceiptStatus::Sample);
        }

        $applicationForm = $source->request_type_id === null ? $this->applications->form($source) : null;

        if ($applicationForm instanceof Form) {
            return $this->apply($receipt, $source, $applicationForm);
        }

        $type = $source->request_type_id === null ? null : RequestType::query()->find($source->request_type_id);

        if (! $source->is_active || ! $type instanceof RequestType) {
            return $this->finish($receipt, ReceiptStatus::Ignored, __('The source is off or not linked to a request type.'));
        }

        $existing = $receipt->entry_id === null ? null : EmployeeRequest::query()->where('cognito_source_id', $source->id)->where('external_entry_id', $receipt->entry_id)->latest('id')->first();

        try {
            return match ($receipt->event) {
                'delete' => $this->delete($receipt, $existing),
                'update' => $existing instanceof EmployeeRequest ? $this->update($receipt, $source, $existing) : $this->submit($receipt, $source, $type, $person),
                default => $existing instanceof EmployeeRequest ? $this->finish($receipt, ReceiptStatus::Duplicate, null, $existing) : $this->submit($receipt, $source, $type, $person),
            };
        } catch (ValidationException $e) {
            return $this->finish($receipt, ReceiptStatus::Failed, $this->describe($e));
        } catch (DomainRuleViolation $e) {
            return $this->finish($receipt, ReceiptStatus::Failed, $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return $this->finish($receipt, ReceiptStatus::Failed, mb_substr($e->getMessage(), 0, 500));
        }
    }

    /**
     * An entry on a posting's Cognito form becomes an application. Submitting again (or an
     * update) refreshes it; a deleted entry leaves it for the recruiter to reject on the board.
     */
    private function apply(CognitoReceipt $receipt, CognitoSource $source, Form $form): CognitoReceipt
    {
        if (! $source->is_active) {
            return $this->finish($receipt, ReceiptStatus::Ignored, __('The source is off or not linked to a request type.'));
        }

        if ($receipt->event === 'delete') {
            return $this->finish($receipt, ReceiptStatus::Ignored, __('Applications stay when the entry is deleted; reject it on the board instead.'));
        }

        $version = $form->published_version_id === null ? null : FormVersion::query()->find($form->published_version_id);

        try {
            $session = "cognito:{$receipt->ulid}";
            $answers = CognitoMapping::of($source)->answers($receipt->payload);

            if ($version instanceof FormVersion) {
                $answers = $this->downloadFiles($answers, $form, $version, $session);
            }

            $receipt->forceFill(['job_application_id' => $this->applications->submit($source, $answers, $session)]);

            return $this->finish($receipt, ReceiptStatus::Processed);
        } catch (ValidationException $e) {
            return $this->finish($receipt, ReceiptStatus::Failed, $this->describe($e));
        } catch (DomainRuleViolation $e) {
            return $this->finish($receipt, ReceiptStatus::Failed, $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return $this->finish($receipt, ReceiptStatus::Failed, mb_substr($e->getMessage(), 0, 500));
        }
    }

    private function submit(CognitoReceipt $receipt, CognitoSource $source, RequestType $type, ?Employee $person): CognitoReceipt
    {
        $mapping = CognitoMapping::of($source);
        $employee = $person ?? ($receipt->employee_id !== null ? Employee::query()->find($receipt->employee_id) : $mapping->employee($receipt->payload));

        if (! $employee instanceof Employee) {
            return $this->finish($receipt, ReceiptStatus::NeedsPerson, __('No employee matches :value.', ['value' => CognitoMapping::scalar($receipt->payload, $mapping->requesterPath) ?? '—']));
        }

        $session = "cognito:{$receipt->ulid}";
        $request = $this->submit->handle($this->actor(), $type, $this->answers($receipt, $mapping, $type, $session), $employee, [
            'source_id' => $source->id, 'entry_id' => $receipt->entry_id, 'session' => $session,
        ]);

        return $this->finish($receipt->forceFill(['employee_id' => $employee->id]), ReceiptStatus::Processed, null, $request);
    }

    private function update(CognitoReceipt $receipt, CognitoSource $source, EmployeeRequest $request): CognitoReceipt
    {
        if (! $request->status->isOpen()) {
            return $this->finish($receipt, ReceiptStatus::LateUpdate, __('The request was already decided, so the change was not applied.'), $request);
        }

        $session = "cognito:{$receipt->ulid}";
        $this->replace->handle($this->actor(), $request, $this->answers($receipt, CognitoMapping::of($source), $request->requestType(), $session), $session);

        return $this->finish($receipt, ReceiptStatus::Processed, null, $request);
    }

    private function delete(CognitoReceipt $receipt, ?EmployeeRequest $request): CognitoReceipt
    {
        $instance = $request?->instance()->first();

        if (! $instance instanceof WorkflowInstance || ! $instance->isOpen()) {
            return $this->finish($receipt, ReceiptStatus::Ignored, __('Nothing running to cancel.'), $request);
        }

        $this->engine->cancel($instance, null, __('The Cognito entry was deleted.'));

        return $this->finish($receipt, ReceiptStatus::Processed, null, $request);
    }

    /**
     * @return array<string, mixed>
     */
    private function answers(CognitoReceipt $receipt, CognitoMapping $mapping, RequestType $type, string $session): array
    {
        $answers = $mapping->answers($receipt->payload);
        $form = Form::withTrashed()->find($type->form_id);
        $version = $form instanceof Form && $form->published_version_id !== null ? FormVersion::query()->find($form->published_version_id) : null;

        if (! $form instanceof Form || ! $version instanceof FormVersion) {
            return $answers;
        }

        return $this->downloadFiles($answers, $form, $version, $session);
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    private function downloadFiles(array $answers, Form $form, FormVersion $version, string $session): array
    {
        foreach (FormSchema::fieldsByKey($version->schema) as $key => $field) {
            if (($field['type'] ?? null) === 'file' && array_key_exists($key, $answers)) {
                $answers[$key] = $this->files->download($answers[$key], $form, $version, $key, $session);
            }
        }

        return $answers;
    }

    private function finish(CognitoReceipt $receipt, ReceiptStatus $status, ?string $error = null, ?EmployeeRequest $request = null): CognitoReceipt
    {
        $receipt->forceFill([
            'status' => $status,
            'error' => $error,
            'request_id' => $request->id ?? $receipt->request_id,
            'processed_at' => now(),
        ])->save();

        return $receipt;
    }

    private function describe(ValidationException $e): string
    {
        $lines = [];

        foreach ($e->errors() as $field => $messages) {
            $texts = [];

            foreach (is_array($messages) ? $messages : [$messages] as $message) {
                if (is_string($message)) {
                    $texts[] = $message;
                }
            }

            $lines[] = $field.': '.implode(' ', $texts);
        }

        return implode("\n", $lines);
    }

    private function actor(): Actor
    {
        return Actor::integration('cognito', Channel::Cognito);
    }
}
