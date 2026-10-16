<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormVersion;
use App\Domain\Hiring\Data\JobPostingInputData;
use App\Domain\Hiring\Enums\Workplace;
use App\Domain\Hiring\Models\JobPosting;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Support\ApplicantFields;
use App\Domain\Hiring\Support\HiringSettings;
use App\Domain\Hiring\Support\Knockouts;
use App\Domain\Integrations\Models\CognitoSource;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Support\Str;

/**
 * Creates or edits a posting for a requisition (D81, D87). The application form must be
 * published and ask for a name and an email; knock-outs must check questions on it. The slug
 * is fixed when the posting is created, so shared links keep working. A posting can take its
 * applications through a Cognito form instead (D81): a source with no request type, feeding no
 * other posting.
 */
final readonly class SaveJobPosting
{
    public function __construct(private HiringSettings $settings) {}

    public function handle(Actor $actor, JobRequisition $requisition, JobPostingInputData $input, ?JobPosting $posting = null): JobPosting
    {
        $form = $input->formId === null ? $this->settings->defaultForm() : Form::query()->where('ulid', $input->formId)->first();
        $version = $form instanceof Form && $form->published_version_id !== null ? FormVersion::query()->find($form->published_version_id) : null;

        if (! $version instanceof FormVersion || ! ApplicantFields::of($version->schema)->usable()) {
            $message = __('Choose a published form that asks for a name and an email.');

            throw new DomainRuleViolation('hiring.form_unusable', $message, ['formId' => [$message]]);
        }

        $problems = Knockouts::problems($input->knockouts, $version->schema);

        if ($problems !== []) {
            throw new DomainRuleViolation('hiring.knockouts', reset($problems), array_map(fn (string $p): array => [$p], $problems));
        }

        $posting ??= new JobPosting(['job_requisition_id' => $requisition->id]);
        $cognito = $input->cognitoSourceId === null ? null : CognitoSource::query()->where('ulid', $input->cognitoSourceId)->first();

        if ($cognito instanceof CognitoSource && ($cognito->request_type_id !== null
            || JobPosting::query()->where('cognito_source_id', $cognito->id)->when($posting->exists, fn ($q) => $q->whereKeyNot($posting->id))->exists())) {
            $message = __('This Cognito source already feeds a request type or another posting.');

            throw new DomainRuleViolation('hiring.cognito_in_use', $message, ['cognitoSourceId' => [$message]]);
        }
        $both = fn (?array $text): ?array => $text === null || ($text['en'] ?? '') === '' ? null : ['en' => $text['en'], 'ar' => ($text['ar'] ?? null) ?: $text['en']];

        $posting->fill([
            'title' => $both($input->title),
            'description' => $both($input->description),
            'summary' => $both($input->summary),
            'location' => $both($input->location),
            'workplace' => Workplace::from($input->workplace),
            'show_salary' => $input->showSalary,
            'form_id' => $form->id,
            'knockouts' => $input->knockouts === [] ? null : $input->knockouts,
            'seo' => $input->seo,
            'closes_on' => $input->closesOn,
            'cognito_source_id' => $cognito?->id,
        ]);

        if (! $posting->exists) {
            $posting->ulid = Str::lower((string) Str::ulid());
            $base = Str::slug($input->title['en']) ?: 'job';
            $posting->slug = Str::limit($base, 100, '').'-'.strtolower(substr($posting->ulid, -6));
        }

        $posting->save();

        activity('hiring')->causedBy($actor->user)->performedOn($posting)->event($posting->wasRecentlyCreated ? 'created' : 'updated')
            ->withProperties(['requisition' => $requisition->number])->log('Posting saved');

        return $posting;
    }
}
