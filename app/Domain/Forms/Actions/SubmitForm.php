<?php

declare(strict_types=1);

namespace App\Domain\Forms\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Enums\SubmissionSource;
use App\Domain\Forms\Events\FormSubmitted;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Forms\Models\FormUpload;
use App\Domain\Forms\Models\FormVersion;
use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Forms\Support\FormContext;
use App\Domain\Forms\Support\FormRulesBuilder;
use App\Domain\People\Models\Employee;
use App\Support\Actors\Actor;
use Illuminate\Database\ConnectionInterface;

/**
 * Records answers to a form version after authoritative server validation (ADR-0008): hidden
 * fields dropped, calculated fields recomputed, uploads checked to belong to this person and
 * field, then claimed by the submission.
 */
final readonly class SubmitForm
{
    public function __construct(
        private ConnectionInterface $db,
        private FormRulesBuilder $rules,
        private FormContext $context,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Actor $actor, Form $form, FormVersion $version, array $input, SubmissionSource $source, ?string $sessionHash = null, ?string $ip = null): FormSubmission
    {
        $user = $actor->user;
        $employee = $user instanceof User ? Employee::query()->where('user_id', $user->id)->first() : null;
        $context = $source === SubmissionSource::Public ? ['requester' => null, 'ctx' => $this->context->forEmployee(null)['ctx']] : $this->context->forEmployee($employee);
        /** @var list<int> $claimed */
        $claimed = [];

        $files = function (string $key, array $tokens, array $field) use ($form, $user, $sessionHash, &$claimed): array|string {
            $max = FieldSpec::int(FieldSpec::validation($field), 'maxFiles', 1);
            $uploads = FormUpload::query()
                ->where('form_id', $form->id)
                ->where('field_key', $key)
                ->whereIn('ulid', $tokens)
                ->whereNull('form_submission_id')
                ->where('expires_at', '>', now())
                ->when($user instanceof User, fn ($q) => $q->where('user_id', $user?->id), fn ($q) => $q->whereNull('user_id')->where('session_hash', $sessionHash))
                ->get();

            if ($uploads->count() !== count(array_unique(array_filter($tokens, is_string(...)))) || count($tokens) > $max) {
                return __('Upload the file again; this one has expired or is not yours.');
            }

            foreach ($uploads as $upload) {
                $claimed[] = $upload->id;
            }

            return array_values($uploads->map(fn (FormUpload $upload): array => [
                'id' => $upload->ulid, 'name' => $upload->original_name, 'size' => $upload->size, 'mime' => $upload->mime,
            ])->all());
        };

        $data = $this->rules->validate($version->schema, $input, $context, $files);

        return $this->db->transaction(function () use ($actor, $form, $version, $data, $source, $employee, $ip, &$claimed): FormSubmission {
            $submission = new FormSubmission([
                'form_id' => $form->id,
                'form_version_id' => $version->id,
                'data' => $data,
                'source' => $source,
                'submitted_by' => $actor->user?->id,
                'employee_id' => $employee?->id,
                'ip_hash' => $ip === null ? null : hash_hmac('sha256', $ip, FieldSpec::string(config('app.key'))),
                'locale' => app()->getLocale(),
            ]);
            $submission->save();

            FormUpload::query()->whereIn('id', array_unique($claimed))->update(['form_submission_id' => $submission->id]);

            activity('forms')->causedBy($actor->user)->performedOn($form)->event('submitted')
                ->withProperties(['submission' => $submission->ulid, 'source' => $source->value, 'version' => $version->number])
                ->log('Form submitted');

            event(new FormSubmitted($submission));

            return $submission;
        });
    }
}
