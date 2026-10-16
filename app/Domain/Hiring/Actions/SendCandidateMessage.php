<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Hiring\Enums\MessageChannel;
use App\Domain\Hiring\Enums\MessageDirection;
use App\Domain\Hiring\Enums\MessagePurpose;
use App\Domain\Hiring\Enums\MessageStatus;
use App\Domain\Hiring\Mail\CandidateMail;
use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\CandidateMessage;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Support\CandidateLinks;
use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\WhatsApp\OutgoingMessage;
use App\Domain\Integrations\WhatsApp\WhatsAppGateway;
use App\Domain\Setup\Support\Settings;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Writes to a candidate (D94) by email, or WhatsApp when they gave a number, and logs it in their
 * communications log whatever happens. WhatsApp tries the official API, then the unofficial
 * driver; outside the 24-hour window the official API can't send free text, so that falls through.
 */
final readonly class SendCandidateMessage
{
    public function __construct(private WhatsAppGateway $whatsapp, private Settings $settings) {}

    /**
     * @param  list<array{label: string, url: string}>  $buttons  links shown as buttons (email) or appended (WhatsApp)
     */
    public function handle(
        Actor $actor,
        Candidate $candidate,
        ?JobApplication $application,
        MessageChannel $channel,
        ?string $subject,
        string $body,
        MessagePurpose $purpose,
        array $buttons = [],
        ?string $ics = null,
    ): CandidateMessage {
        if ($candidate->anonymised_at !== null) {
            throw new DomainRuleViolation('hiring.anonymised', __('This candidate’s details were deleted.'));
        }

        $to = $channel === MessageChannel::Email ? $candidate->email : $candidate->phone;

        if ($to === null || $to === '') {
            $message = $channel === MessageChannel::Email ? __('This candidate has no email address.') : __('This candidate has no mobile number.');

            throw new DomainRuleViolation('hiring.no_contact', $message, ['channel' => [$message]]);
        }

        $locale = $candidate->locale === 'ar' ? 'ar' : 'en';
        $company = FieldSpec::text($this->settings->get('company.name'), $locale, FieldSpec::string(config('app.name'), 'Mawred'));
        [$status, $error] = $channel === MessageChannel::Email
            ? $this->email($to, $subject ?? $company, $body, $locale, $company, CandidateLinks::privacy($candidate), $buttons, $ics)
            : $this->whatsApp($actor, $to, $body, $buttons);

        $log = CandidateMessage::query()->create([
            'candidate_id' => $candidate->id,
            'job_application_id' => $application?->id,
            'direction' => MessageDirection::Out,
            'channel' => $channel,
            'purpose' => $purpose,
            'subject' => $channel === MessageChannel::Email ? mb_substr($subject ?? $company, 0, 200) : null,
            'body' => $body,
            'status' => $status,
            'error' => $error === null ? null : mb_substr($error, 0, 300),
            'sent_by_user_id' => $actor->user?->id,
            'created_at' => now(),
        ]);

        $candidate->forceFill(['last_activity_at' => now()])->save();

        return $log;
    }

    /**
     * @param  list<array{label: string, url: string}>  $buttons
     * @return array{0: MessageStatus, 1: string|null}
     */
    private function email(string $to, string $subject, string $body, string $locale, string $company, string $privacy, array $buttons, ?string $ics): array
    {
        try {
            Mail::to($to)->queue(new CandidateMail($subject, $body, $locale, $company, $privacy, $buttons, $ics));
        } catch (Throwable $e) {
            report($e);

            return [MessageStatus::Failed, $e->getMessage()];
        }

        return [MessageStatus::Sent, null];
    }

    /**
     * @param  list<array{label: string, url: string}>  $buttons
     * @return array{0: MessageStatus, 1: string|null}
     */
    private function whatsApp(Actor $actor, string $to, string $body, array $buttons): array
    {
        $text = $body.implode('', array_map(fn (array $b): string => "\n\n{$b['label']}: {$b['url']}", $buttons));
        $error = __('WhatsApp isn’t set up.');

        foreach ([IntegrationType::WhatsAppOfficial, IntegrationType::WhatsAppWaha] as $driver) {
            if (WhatsAppGateway::usable($driver) === null) {
                continue;
            }

            $result = $this->whatsapp->send($driver, $actor->user, 'candidate', new OutgoingMessage($to, $text));

            if ($result->sent) {
                return [MessageStatus::Sent, null];
            }

            $error = $result->error ?? $error;
        }

        return [MessageStatus::Failed, $error];
    }
}
