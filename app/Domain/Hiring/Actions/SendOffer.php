<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Hiring\Enums\ApplicationEventKind;
use App\Domain\Hiring\Enums\MessageChannel;
use App\Domain\Hiring\Enums\MessagePurpose;
use App\Domain\Hiring\Enums\OfferStatus;
use App\Domain\Hiring\Models\ApplicationEvent;
use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\Offer;
use App\Domain\Hiring\Support\CandidateLinks;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

/** Sends an approved offer: the candidate gets a link to read and sign it (D83), by email or WhatsApp. */
final readonly class SendOffer
{
    public function __construct(private SendCandidateMessage $messages) {}

    public function handle(Actor $actor, Offer $offer, MessageChannel $channel): Offer
    {
        if (! in_array($offer->status, [OfferStatus::Approved, OfferStatus::Sent], true)) {
            throw new DomainRuleViolation('hiring.offer_not_approved', __('An offer goes out only after it is approved.'));
        }

        /** @var JobApplication $application */
        $application = $offer->application()->with('candidate')->firstOrFail();
        /** @var Candidate $candidate */
        $candidate = $application->candidate;
        $locale = $offer->locale;

        $this->messages->handle(
            $actor,
            $candidate,
            $application,
            $channel,
            __('Your job offer: :job', ['job' => $offer->job_title], $locale),
            __("Hello :name,\n\nWe are pleased to offer you the position of :job. Open the offer to read it and sign it online.", ['name' => $candidate->name, 'job' => $offer->job_title], $locale)
                .($offer->expires_on === null ? '' : "\n\n".__('The offer is valid until :date.', ['date' => $offer->expires_on->toDateString()], $locale)),
            MessagePurpose::Offer,
            [['label' => __('Read and sign the offer', [], $locale), 'url' => CandidateLinks::offer($offer)]],
        );

        $offer->forceFill(['status' => OfferStatus::Sent, 'sent_at' => now()])->save();

        ApplicationEvent::query()->create([
            'job_application_id' => $application->id,
            'kind' => ApplicationEventKind::Offer,
            'actor_user_id' => $actor->user?->id,
            'data' => ['offer' => $offer->ulid, 'event' => 'sent', 'channel' => $channel->value],
            'created_at' => now(),
        ]);

        activity('hiring')->causedBy($actor->user)->performedOn($offer)->event('sent')->withProperties(['channel' => $channel->value])->log('Offer sent');

        return $offer;
    }
}
