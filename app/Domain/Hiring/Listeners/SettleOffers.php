<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Listeners;

use App\Domain\Hiring\Actions\SettleOffer;
use App\Domain\Hiring\Models\Offer;
use App\Domain\Workflows\Enums\Outcome;
use App\Domain\Workflows\Events\InstanceFinished;

/** Marks an offer approved or not when its approval workflow ends (D91). */
final readonly class SettleOffers
{
    public function __construct(private SettleOffer $settle) {}

    public function handle(InstanceFinished $event): void
    {
        $instance = $event->instance;

        if ($instance->subject_type !== Offer::class || $instance->subject_id === null) {
            return;
        }

        $offer = Offer::query()->find($instance->subject_id);

        if ($offer instanceof Offer && in_array($instance->outcome, [Outcome::Approved, Outcome::Rejected], true)) {
            $this->settle->handle($offer, $instance->outcome === Outcome::Approved);
        }
    }
}
