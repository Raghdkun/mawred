<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Hiring\Enums\OfferStatus;
use App\Domain\Hiring\Models\Offer;
use App\Domain\Hiring\Support\OfferResponses;

/** The candidate declines on the offer's link, optionally saying why. */
final readonly class DeclineOffer
{
    public function __construct(private OfferResponses $responses) {}

    public function handle(Offer $offer, ?string $reason): Offer
    {
        $this->responses->assertOpen($offer);

        $offer->forceFill([
            'status' => OfferStatus::Declined,
            'responded_at' => now(),
            'decline_reason' => $reason === null || trim($reason) === '' ? null : mb_substr(trim($reason), 0, 2000),
        ])->save();

        $this->responses->record($offer, 'declined');
        activity('hiring')->performedOn($offer)->event('declined')->log('Offer declined');

        return $offer;
    }
}
