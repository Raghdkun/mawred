<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Hiring\Enums\PostingStatus;
use App\Domain\Hiring\Enums\RequisitionStatus;
use App\Domain\Hiring\Models\JobPosting;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

/** Publishes a posting on the careers site (only while its requisition is open) or closes it. */
final readonly class ChangePostingStatus
{
    public function handle(Actor $actor, JobPosting $posting, PostingStatus $to): JobPosting
    {
        if ($to === PostingStatus::Published && $posting->requisition()->firstOrFail()->status !== RequisitionStatus::Open) {
            $message = __('Postings can go live only while the requisition is open.');

            throw new DomainRuleViolation('hiring.requisition_not_open', $message, ['status' => [$message]]);
        }

        $from = $posting->status;
        $posting->forceFill([
            'status' => $to,
            'published_at' => $to === PostingStatus::Published ? ($posting->published_at ?? now()) : $posting->published_at,
        ])->save();

        activity('hiring')->causedBy($actor->user)->performedOn($posting)->event('status_changed')
            ->withProperties(['from' => $from->value, 'to' => $to->value])->log('Posting status changed');

        return $posting;
    }
}
