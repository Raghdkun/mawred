<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Hiring\Data\OfferInputData;
use App\Domain\Hiring\Enums\ApplicationStatus;
use App\Domain\Hiring\Enums\OfferStatus;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\Offer;
use App\Domain\Hiring\Support\OfferLetter;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use App\Support\Money\Money;
use Illuminate\Database\ConnectionInterface;

/**
 * Drafts an offer or edits a draft (D95). The letter is filled from the template in the
 * candidate's language when left empty; the recruiter may adjust it before approval. One live
 * offer per application at a time.
 */
final readonly class SaveOffer
{
    public function __construct(private OfferLetter $letters, private ConnectionInterface $db) {}

    public function handle(Actor $actor, JobApplication $application, OfferInputData $input, ?Offer $offer = null): Offer
    {
        if ($application->status !== ApplicationStatus::Active) {
            throw new DomainRuleViolation('hiring.not_active', __('Only candidates still on the board can get an offer.'));
        }

        if ($offer instanceof Offer && $offer->status !== OfferStatus::Draft) {
            throw new DomainRuleViolation('hiring.offer_locked', __('Only a draft offer can be changed. Withdraw it and draft a new one.'));
        }

        if (! $offer instanceof Offer && $application->offers()->get()->contains(fn (Offer $o): bool => $o->status->isLive())) {
            throw new DomainRuleViolation('hiring.offer_exists', __('This candidate already has an offer in progress.'));
        }

        $candidate = $application->candidate()->firstOrFail();
        $id = function (string $table, ?string $ulid): ?int {
            $value = $ulid === null ? null : $this->db->table($table)->where('ulid', strtolower($ulid))->value('id');

            return is_numeric($value) ? (int) $value : null;
        };

        $offer ??= new Offer([
            'job_application_id' => $application->id,
            'status' => OfferStatus::Draft,
            'locale' => $candidate->locale === 'ar' ? 'ar' : 'en',
            'created_by_user_id' => $actor->user?->id,
        ]);
        $offer->fill([
            'job_title' => trim($input->jobTitle),
            'start_on' => $input->startOn,
            'salary' => new Money($input->salary['amount_minor'], $input->salary['currency']),
            'branch_id' => $id('branches', $input->branchId),
            'department_id' => $id('departments', $input->departmentId),
            'position_id' => $id('positions', $input->positionId),
            'employment_type_id' => $id('employment_types', $input->employmentTypeId),
            'manager_employee_id' => $id('employees', $input->managerId),
            'expires_on' => $input->expiresOn,
        ]);
        $offer->letter = $input->letter !== null && trim($input->letter) !== '' ? trim($input->letter) : $this->letters->render($offer, $candidate);
        $offer->save();

        activity('hiring')->causedBy($actor->user)->performedOn($offer)->event($offer->wasRecentlyCreated ? 'drafted' : 'updated')->log('Offer saved');

        return $offer;
    }
}
