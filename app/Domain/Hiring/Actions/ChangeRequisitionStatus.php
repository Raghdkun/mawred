<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Hiring\Enums\PostingStatus;
use App\Domain\Hiring\Enums\RequisitionStatus;
use App\Domain\Hiring\Models\JobRequisition;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/**
 * HR pauses, reopens, fills or closes a requisition. Leaving "open" takes its postings off the
 * careers site; reopening does not put them back by itself.
 */
final readonly class ChangeRequisitionStatus
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(Actor $actor, JobRequisition $requisition, RequisitionStatus $to): JobRequisition
    {
        if (! in_array($to, $requisition->status->next(), true)) {
            throw new DomainRuleViolation('hiring.bad_status', __('This requisition can’t move to that status.'), ['status' => [__('This requisition can’t move to that status.')]]);
        }

        return $this->db->transaction(function () use ($actor, $requisition, $to): JobRequisition {
            $from = $requisition->status;
            $requisition->forceFill([
                'status' => $to,
                'closed_at' => in_array($to, [RequisitionStatus::Filled, RequisitionStatus::Closed], true) ? now() : null,
            ])->save();

            if ($to !== RequisitionStatus::Open) {
                $requisition->postings()->where('status', PostingStatus::Published)->update(['status' => PostingStatus::Closed, 'updated_at' => now()]);
            }

            activity('hiring')->causedBy($actor->user)->performedOn($requisition)->event('status_changed')
                ->withProperties(['from' => $from->value, 'to' => $to->value])->log('Requisition status changed');

            return $requisition;
        });
    }
}
