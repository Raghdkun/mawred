<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Support;

use App\Domain\Forms\Models\Form;
use App\Domain\Hiring\Actions\SubmitApplication;
use App\Domain\Hiring\Enums\ApplicationSource;
use App\Domain\Hiring\Enums\PostingStatus;
use App\Domain\Hiring\Models\JobPosting;
use App\Domain\Integrations\Contracts\CognitoApplications;
use App\Domain\Integrations\Models\CognitoSource;
use App\Support\Actors\Actor;
use App\Support\Actors\Channel;
use App\Support\Errors\DomainRuleViolation;

/**
 * Applications that arrive through a Cognito form linked to a posting (D81, D97). The answers
 * go through the posting's Mawred form like any application; the hosted form is responsible for
 * showing the privacy notice, so the privacy consent is recorded as given.
 */
final readonly class CognitoPostings implements CognitoApplications
{
    public function __construct(private SubmitApplication $submit, private HiringSettings $settings) {}

    public function posting(CognitoSource $source, string $locale): ?string
    {
        $posting = $this->find($source);

        return $posting instanceof JobPosting ? Texts::one($posting, 'title', $locale) : null;
    }

    public function form(CognitoSource $source): ?Form
    {
        $posting = $this->find($source);

        if (! $posting instanceof JobPosting) {
            return null;
        }

        return $posting->form()->first() ?? $this->settings->defaultForm();
    }

    public function submit(CognitoSource $source, array $answers, string $session): int
    {
        $posting = $this->find($source);

        if (! $posting instanceof JobPosting || $posting->status !== PostingStatus::Published) {
            throw new DomainRuleViolation('hiring.posting_closed', __('This job is no longer taking applications.'));
        }

        $application = $this->submit->handle(
            Actor::integration('cognito', Channel::Cognito),
            $posting->requisition()->firstOrFail(),
            $posting,
            $answers,
            ApplicationSource::Cognito,
            ['privacy' => true, 'talentPool' => false],
            $session,
        );

        return $application->id;
    }

    private function find(CognitoSource $source): ?JobPosting
    {
        return JobPosting::query()->where('cognito_source_id', $source->id)->first();
    }
}
