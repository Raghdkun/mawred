<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Hiring\Data\HireCandidateData;
use App\Domain\Hiring\Enums\ApplicationEventKind;
use App\Domain\Hiring\Enums\ApplicationStatus;
use App\Domain\Hiring\Enums\OfferStatus;
use App\Domain\Hiring\Enums\RequisitionStatus;
use App\Domain\Hiring\Enums\StageKind;
use App\Domain\Hiring\Models\ApplicationEvent;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Models\Offer;
use App\Domain\Hiring\Models\PipelineStage;
use App\Domain\People\Actions\HireEmployee;
use App\Domain\People\Actions\RecordSalaryChange;
use App\Domain\People\Data\HireEmployeeData;
use App\Domain\People\Data\PlacementInputData;
use App\Domain\People\Models\Employee;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use App\Support\Money\Money;
use Illuminate\Database\ConnectionInterface;

/**
 * HR confirms the hire after the candidate accepted (D85): the employee is created with the hire
 * date (onboarding starts from `EmployeeHired`), the offer's pay becomes their starting salary,
 * the application moves to "Hired", and the requisition is marked filled once every seat is.
 */
final readonly class HireCandidate
{
    public function __construct(
        private ConnectionInterface $db,
        private HireEmployee $hire,
        private RecordSalaryChange $salaries,
    ) {}

    public function handle(Actor $actor, JobApplication $application, HireCandidateData $input): Employee
    {
        /** @var Offer|null $offer */
        $offer = $application->offers()->where('status', OfferStatus::Accepted)->first();

        if ($application->status !== ApplicationStatus::Active || ! $offer instanceof Offer) {
            throw new DomainRuleViolation('hiring.not_hireable', __('Hire a candidate once they have accepted the offer.'));
        }

        if ($application->employee_id !== null) {
            throw new DomainRuleViolation('hiring.already_hired', __('This candidate is already an employee.'));
        }

        return $this->db->transaction(function () use ($actor, $application, $offer, $input): Employee {
            $employee = $this->hire->handle($actor, HireEmployeeData::from([
                'nameEn' => $input->nameEn,
                'nameAr' => $input->nameAr,
                'hireDate' => $input->hireDate,
                'workEmail' => $input->workEmail,
                'placement' => PlacementInputData::from([
                    'branchId' => $input->branchId,
                    'departmentId' => $input->departmentId,
                    'positionId' => $input->positionId,
                    'managerId' => $input->managerId,
                    'employmentTypeId' => $input->employmentTypeId,
                ]),
            ]));

            if ($offer->salary instanceof Money) {
                // The approved offer is the authority for the starting salary (D91), whoever confirms the hire.
                $this->salaries->handle(Actor::system('hiring:'.$offer->ulid), $employee, $offer->salary, $input->hireDate, __('Starting salary (accepted offer)'));
            }

            $hired = PipelineStage::query()->where('job_requisition_id', $application->job_requisition_id)->where('kind', StageKind::Hired)->first();
            $from = $application->pipeline_stage_id;
            $application->forceFill([
                'status' => ApplicationStatus::Hired,
                'employee_id' => $employee->id,
                'pipeline_stage_id' => $hired->id ?? $from,
                'stage_entered_at' => now(),
                'last_activity_at' => now(),
            ])->save();

            ApplicationEvent::query()->create([
                'job_application_id' => $application->id,
                'kind' => ApplicationEventKind::Hired,
                'from_stage_id' => $from,
                'to_stage_id' => $hired?->id,
                'actor_user_id' => $actor->user?->id,
                'data' => ['employee' => $employee->ulid, 'hire_date' => $input->hireDate],
                'created_at' => now(),
            ]);

            /** @var JobRequisition $requisition */
            $requisition = $application->requisition()->lockForUpdate()->firstOrFail();
            $seats = JobApplication::query()->where('job_requisition_id', $requisition->id)->where('status', ApplicationStatus::Hired)->count();

            if ($requisition->status === RequisitionStatus::Open && $seats >= $requisition->headcount) {
                $requisition->forceFill(['status' => RequisitionStatus::Filled, 'closed_at' => now()])->save();
                $requisition->postings()->where('status', 'published')->update(['status' => 'closed', 'updated_at' => now()]);
            }

            activity('hiring')->causedBy($actor->user)->performedOn($application)->event('hired')
                ->withProperties(['employee' => $employee->ulid])->log('Candidate hired');

            return $employee;
        });
    }
}
