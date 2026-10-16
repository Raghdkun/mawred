<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Hiring\Enums\MemberRole;
use App\Domain\Hiring\Enums\RequisitionStatus;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Models\PipelineStage;
use App\Domain\Hiring\Models\RequisitionMember;
use App\Domain\Hiring\Models\ScorecardCriterion;
use App\Domain\Hiring\Support\RequisitionAnswers;
use App\Domain\Hiring\Support\RequisitionNumbers;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Support\Actors\Actor;
use Illuminate\Database\ConnectionInterface;

/**
 * The approval chain said yes (D84): the requisition opens with a copy of the default pipeline,
 * and the person who asked joins its hiring team as hiring manager. A request raised outside the
 * Hiring screen gets its requisition here. Running it twice changes nothing.
 */
final readonly class OpenRequisition
{
    public function __construct(private ConnectionInterface $db, private RequisitionNumbers $numbers) {}

    public function handle(Actor $actor, EmployeeRequest $request): JobRequisition
    {
        return $this->db->transaction(function () use ($actor, $request): JobRequisition {
            $requisition = JobRequisition::query()->where('request_id', $request->id)->lockForUpdate()->first();

            if (! $requisition instanceof JobRequisition) {
                $submission = $request->submission()->first();
                $answers = $submission instanceof FormSubmission ? $submission->data : null;
                $requisition = new JobRequisition([
                    ...RequisitionAnswers::attributes(is_array($answers) ? $answers : [], Employee::query()->find($request->requester_employee_id), $request->number),
                    'number' => $this->numbers->next(),
                    'requested_by_user_id' => $request->requester_user_id,
                    'request_id' => $request->id,
                ]);
            }

            if ($requisition->exists && $requisition->status !== RequisitionStatus::Pending) {
                return $requisition;
            }

            $requisition->forceFill(['status' => RequisitionStatus::Open, 'opened_at' => now()])->save();

            if (! $requisition->stages()->exists()) {
                foreach (PipelineStage::query()->whereNull('job_requisition_id')->orderBy('position')->get() as $template) {
                    PipelineStage::query()->create([
                        'job_requisition_id' => $requisition->id,
                        'name' => $template->getTranslations('name'),
                        'kind' => $template->kind,
                        'position' => $template->position,
                        'automation' => $template->automation,
                    ]);
                }
            }

            if (! $requisition->criteria()->exists()) {
                foreach (ScorecardCriterion::query()->whereNull('job_requisition_id')->orderBy('position')->get() as $template) {
                    ScorecardCriterion::query()->create([
                        'job_requisition_id' => $requisition->id,
                        'name' => $template->getTranslations('name'),
                        'position' => $template->position,
                    ]);
                }
            }

            if ($requisition->requested_by_user_id !== null) {
                RequisitionMember::query()->firstOrCreate(
                    ['job_requisition_id' => $requisition->id, 'user_id' => $requisition->requested_by_user_id],
                    ['role' => MemberRole::HiringManager],
                );
            }

            activity('hiring')->causedBy($actor->user)->performedOn($requisition)->event('opened')
                ->withProperties(['number' => $requisition->number, 'channel' => $actor->channel->value])->log('Requisition opened');

            return $requisition;
        });
    }
}
