<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Models\ScorecardCriterion;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/**
 * The criteria interviewers rate (D90), for one requisition or the defaults new requisitions
 * copy. Removing a criterion keeps the ratings already given against it.
 */
final readonly class SaveScorecardCriteria
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * @param  list<array{id?: string|null, name: array{en: string, ar?: string|null}}>  $criteria
     */
    public function handle(Actor $actor, ?JobRequisition $requisition, array $criteria): void
    {
        if ($criteria === [] || count($criteria) > 10) {
            $message = __('Use between one and ten criteria.');

            throw new DomainRuleViolation('hiring.criteria_count', $message, ['criteria' => [$message]]);
        }

        $this->db->transaction(function () use ($actor, $requisition, $criteria): void {
            $existing = ScorecardCriterion::query()
                ->when($requisition instanceof JobRequisition, fn ($q) => $q->where('job_requisition_id', $requisition?->id), fn ($q) => $q->whereNull('job_requisition_id'))
                ->get()
                ->keyBy('ulid');
            $kept = [];

            foreach ($criteria as $position => $row) {
                $model = is_string($row['id'] ?? null) ? $existing->get($row['id']) : null;
                $model ??= new ScorecardCriterion(['job_requisition_id' => $requisition?->id]);
                $model->fill([
                    'name' => ['en' => $row['name']['en'], 'ar' => ($row['name']['ar'] ?? null) ?: $row['name']['en']],
                    'position' => $position,
                ])->save();
                $kept[] = $model->id;
            }

            ScorecardCriterion::query()->whereKey($existing->reject(fn (ScorecardCriterion $c): bool => in_array($c->id, $kept, true))->modelKeys())->delete();

            $log = activity('hiring')->causedBy($actor->user)->event('criteria_changed')
                ->withProperties(['criteria' => count($criteria), 'template' => ! $requisition instanceof JobRequisition]);

            if ($requisition instanceof JobRequisition) {
                $log->performedOn($requisition);
            }

            $log->log('Scorecard criteria changed');
        });
    }
}
