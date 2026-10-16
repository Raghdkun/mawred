<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Support;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Hiring\Enums\ApplicationEventKind;
use App\Domain\Hiring\Enums\OfferStatus;
use App\Domain\Hiring\Models\ApplicationEvent;
use App\Domain\Hiring\Models\Offer;
use App\Domain\Organization\Models\Branch;
use App\Support\Errors\DomainRuleViolation;
use App\Support\Time\LocalClock;

/** Shared by accepting and declining an offer: is it still open, and recording the answer. */
final readonly class OfferResponses
{
    public function __construct(private HiringNotifier $notifier, private LocalClock $clock) {}

    public function assertOpen(Offer $offer): void
    {
        if ($offer->status === OfferStatus::Sent && $offer->expires_on !== null && $offer->expires_on->toDateString() < $this->clock->today($offer->branch instanceof Branch ? $offer->branch->timezone : FieldSpec::string(config('app.timezone'), 'UTC'))) {
            $offer->forceFill(['status' => OfferStatus::Expired])->save();
        }

        if ($offer->status !== OfferStatus::Sent) {
            throw new DomainRuleViolation('hiring.offer_closed', __('This offer is no longer open.'));
        }
    }

    /**
     * @param  'accepted'|'declined'  $what
     */
    public function record(Offer $offer, string $what): void
    {
        ApplicationEvent::query()->create([
            'job_application_id' => $offer->job_application_id,
            'kind' => ApplicationEventKind::Offer,
            'data' => ['offer' => $offer->ulid, 'event' => $what],
            'body' => $what === 'declined' ? $offer->decline_reason : null,
            'created_at' => now(),
        ]);

        $author = $offer->created_by_user_id === null ? null : User::query()->find($offer->created_by_user_id);

        if ($author instanceof User) {
            $this->notifier->offer($author, $offer, $what);
        }
    }
}
