<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Hiring\Data\HiringSettingsData;
use App\Domain\Hiring\Support\CandidateTemplates;
use App\Domain\Hiring\Support\OfferLetter;
use App\Domain\Setup\Support\Settings;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use App\Support\Templates\SafeTemplate;
use InvalidArgumentException;

/**
 * Saves Hiring settings: the careers texts (D87), retention periods (D86), the rejection message
 * (D93) and the offer letter templates (D95), checking the templates use known variables.
 */
final readonly class SaveHiringSettings
{
    public function __construct(private Settings $settings, private CandidateTemplates $candidateTemplates, private SafeTemplate $templates) {}

    public function handle(Actor $actor, HiringSettingsData $data): void
    {
        $both = function (array $text): array {
            $en = FieldSpec::string($text['en'] ?? null);

            return ['en' => $en, 'ar' => FieldSpec::string($text['ar'] ?? null) ?: $en];
        };

        if ($data->rejectionMessage !== null && $data->rejectionMessage['enabled']) {
            $subject = $both($data->rejectionMessage['subject']);
            $body = $both($data->rejectionMessage['body']);
            $this->candidateTemplates->assertValid([$subject['en'], $subject['ar'], $body['en'], $body['ar']], 'rejectionMessage.body.en');
        }

        if ($data->offerLetter !== null) {
            foreach ($both($data->offerLetter) as $locale => $letter) {
                try {
                    $this->templates->assertValid($letter, OfferLetter::VARIABLES);
                } catch (InvalidArgumentException) {
                    $message = __('Use only these variables: :list.', ['list' => implode(', ', array_map(fn (string $v): string => '{{ '.$v.' }}', OfferLetter::VARIABLES))]);

                    throw new DomainRuleViolation('hiring.template', $message, ["offerLetter.{$locale}" => [$message]]);
                }
            }
        }

        $this->settings->set('hiring.careers_intro', $both($data->careersIntro));
        $this->settings->set('hiring.privacy_notice', $both($data->privacyNotice));

        if ($data->retentionMonths !== null) {
            $this->settings->set('hiring.retention_months', $data->retentionMonths);
        }

        if ($data->poolRetentionMonths !== null) {
            $this->settings->set('hiring.pool_retention_months', $data->poolRetentionMonths);
        }

        if ($data->rejectionMessage !== null) {
            $this->settings->set('hiring.rejection_message', [
                'enabled' => $data->rejectionMessage['enabled'],
                'subject' => $both($data->rejectionMessage['subject']),
                'body' => $both($data->rejectionMessage['body']),
            ]);
        }

        if ($data->offerLetter !== null) {
            $this->settings->set('hiring.offer_letter', $both($data->offerLetter));
        }

        activity('hiring')->causedBy($actor->user)->event('settings_changed')->log('Hiring settings changed');
    }
}
