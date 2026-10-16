<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Hiring\Enums\ApplicationEventKind;
use App\Domain\Hiring\Enums\OfferStatus;
use App\Domain\Hiring\Models\ApplicationEvent;
use App\Domain\Hiring\Models\Offer;
use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Models\WorkflowInstance;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

/** Takes an offer back before the candidate answers; a pending approval is cancelled. */
final readonly class WithdrawOffer
{
    public function __construct(private WorkflowEngine $engine) {}

    public function handle(Actor $actor, Offer $offer): Offer
    {
        if (! in_array($offer->status, [OfferStatus::Draft, OfferStatus::PendingApproval, OfferStatus::Approved, OfferStatus::Sent], true)) {
            throw new DomainRuleViolation('hiring.offer_closed', __('This offer can no longer be withdrawn.'));
        }

        $offer->forceFill(['status' => OfferStatus::Withdrawn])->save();
        $instance = $offer->instance()->first();

        if ($instance instanceof WorkflowInstance && $instance->isOpen()) {
            $this->engine->cancel($instance, $actor->user, __('The offer was withdrawn.'));
        }

        ApplicationEvent::query()->create([
            'job_application_id' => $offer->job_application_id,
            'kind' => ApplicationEventKind::Offer,
            'actor_user_id' => $actor->user?->id,
            'data' => ['offer' => $offer->ulid, 'event' => 'withdrawn'],
            'created_at' => now(),
        ]);

        activity('hiring')->causedBy($actor->user)->performedOn($offer)->event('withdrawn')->log('Offer withdrawn');

        return $offer;
    }
}
