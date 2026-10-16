<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Hiring\Enums\ApplicationEventKind;
use App\Domain\Hiring\Enums\OfferStatus;
use App\Domain\Hiring\Models\ApplicationEvent;
use App\Domain\Hiring\Models\Offer;
use App\Domain\Hiring\Support\HiringNotifier;

/**
 * The offer's approval decided (D91): approved offers are ready to send; rejected ones end and
 * a new offer can be drafted. The person who drafted it is told either way.
 */
final readonly class SettleOffer
{
    public function __construct(private HiringNotifier $notifier) {}

    public function handle(Offer $offer, bool $approved): void
    {
        if ($offer->status !== OfferStatus::PendingApproval) {
            return;
        }

        $offer->forceFill(['status' => $approved ? OfferStatus::Approved : OfferStatus::Rejected])->save();

        ApplicationEvent::query()->create([
            'job_application_id' => $offer->job_application_id,
            'kind' => ApplicationEventKind::Offer,
            'data' => ['offer' => $offer->ulid, 'event' => $approved ? 'approved' : 'rejected'],
            'created_at' => now(),
        ]);

        activity('hiring')->performedOn($offer)->event($approved ? 'approved' : 'rejected')->log($approved ? 'Offer approved' : 'Offer not approved');

        $author = $offer->created_by_user_id === null ? null : User::query()->find($offer->created_by_user_id);

        if ($author instanceof User) {
            $this->notifier->offer($author, $offer, $approved ? 'approved' : 'rejected');
        }
    }
}
