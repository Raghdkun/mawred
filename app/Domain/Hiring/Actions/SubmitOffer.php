<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Hiring\Enums\ApplicationEventKind;
use App\Domain\Hiring\Enums\OfferStatus;
use App\Domain\Hiring\Models\ApplicationEvent;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Models\Offer;
use App\Domain\People\Models\Employee;
use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Models\WorkflowDefinition;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use App\Support\Money\Money;
use Illuminate\Database\ConnectionInterface;

/**
 * Sends a draft offer for approval (D91): the seeded Job offer workflow asks the requisition's
 * hiring manager, then HR, in their approvals inboxes. HR can reshape the chain.
 */
final readonly class SubmitOffer
{
    public const string WORKFLOW = 'JOB_OFFER';

    public function __construct(private ConnectionInterface $db, private WorkflowEngine $engine) {}

    public function handle(Actor $actor, Offer $offer): Offer
    {
        if ($offer->status !== OfferStatus::Draft) {
            throw new DomainRuleViolation('hiring.offer_locked', __('Only a draft offer can be sent for approval.'));
        }

        $workflow = WorkflowDefinition::query()->where('code', self::WORKFLOW)->first();

        if (! $workflow instanceof WorkflowDefinition || $workflow->published_version_id === null) {
            throw new DomainRuleViolation('hiring.no_offer_workflow', __('The Job offer workflow isn’t published. Ask HR to publish it.'));
        }

        /** @var JobApplication $application */
        $application = $offer->application()->with(['candidate', 'requisition'])->firstOrFail();
        /** @var JobRequisition $requisition */
        $requisition = $application->requisition;
        $manager = $requisition->hiring_manager_employee_id === null ? null : Employee::query()->find($requisition->hiring_manager_employee_id);
        $requester = $actor->user instanceof User ? Employee::query()->where('user_id', $actor->user->id)->first() : null;

        return $this->db->transaction(function () use ($actor, $offer, $application, $requisition, $manager, $requester, $workflow): Offer {
            $salary = $offer->salary;
            $instance = $this->engine->start($workflow, [
                'field' => [
                    'hiring_manager' => $manager?->ulid,
                    'candidate' => $application->candidate?->name,
                    'job' => $offer->job_title,
                    'start_on' => $offer->start_on->toDateString(),
                    'salary' => $salary instanceof Money ? ['amount_minor' => $salary->minor, 'currency' => $salary->currency] : null,
                ],
                'offer' => ['id' => $offer->ulid, 'requisition' => $requisition->number],
            ], $actor->user, $requester, $offer);

            $offer->forceFill(['status' => OfferStatus::PendingApproval, 'workflow_instance_id' => $instance->id])->save();

            ApplicationEvent::query()->create([
                'job_application_id' => $application->id,
                'kind' => ApplicationEventKind::Offer,
                'actor_user_id' => $actor->user?->id,
                'data' => ['offer' => $offer->ulid, 'event' => 'submitted'],
                'created_at' => now(),
            ]);

            activity('hiring')->causedBy($actor->user)->performedOn($offer)->event('submitted')->log('Offer sent for approval');

            return $offer;
        });
    }
}
