<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Hiring\Enums\StageKind;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Models\PipelineStage;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;

/**
 * Renames, adds, reorders and removes the stages of a requisition's board, or of the default
 * template when no requisition is given. The first stage stays "applied" and the last "hired";
 * a stage with candidates in it can't be removed.
 */
final readonly class SavePipelineStages
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * @param  list<array{id?: string|null, name: array{en: string, ar?: string|null}, kind: string}>  $stages
     */
    public function handle(Actor $actor, ?JobRequisition $requisition, array $stages): void
    {
        $kinds = array_map(fn (array $s): StageKind => StageKind::from($s['kind']), $stages);
        $count = array_count_values(array_map(fn (StageKind $k): string => $k->value, $kinds));

        if ($kinds === [] || $kinds[0] !== StageKind::Applied || end($kinds) !== StageKind::Hired || ($count['applied'] ?? 0) !== 1 || ($count['hired'] ?? 0) !== 1) {
            $message = __('Start with one “Applied” stage and end with one “Hired” stage.');

            throw new DomainRuleViolation('hiring.stage_order', $message, ['stages' => [$message]]);
        }

        $this->db->transaction(function () use ($actor, $requisition, $stages): void {
            $existing = PipelineStage::query()
                ->when($requisition instanceof JobRequisition, fn ($q) => $q->where('job_requisition_id', $requisition?->id), fn ($q) => $q->whereNull('job_requisition_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('ulid');
            $kept = [];

            foreach ($stages as $position => $stage) {
                $model = is_string($stage['id'] ?? null) ? $existing->get($stage['id']) : null;
                $model ??= new PipelineStage(['job_requisition_id' => $requisition?->id]);
                $model->fill([
                    'name' => ['en' => $stage['name']['en'], 'ar' => ($stage['name']['ar'] ?? null) ?: $stage['name']['en']],
                    'kind' => StageKind::from($stage['kind']),
                    'position' => $position,
                ])->save();
                $kept[] = $model->id;
            }

            $removed = $existing->reject(fn (PipelineStage $s): bool => in_array($s->id, $kept, true));

            if ($removed->isNotEmpty() && JobApplication::query()->whereIn('pipeline_stage_id', $removed->modelKeys())->exists()) {
                $message = __('Move the candidates out of a stage before removing it.');

                throw new DomainRuleViolation('hiring.stage_not_empty', $message, ['stages' => [$message]]);
            }

            PipelineStage::query()->whereKey($removed->modelKeys())->delete();

            if ($requisition instanceof JobRequisition) {
                activity('hiring')->causedBy($actor->user)->performedOn($requisition)->event('stages_changed')
                    ->withProperties(['stages' => count($stages)])->log('Pipeline stages changed');
            } else {
                activity('hiring')->causedBy($actor->user)->event('stages_changed')->withProperties(['template' => true, 'stages' => count($stages)])->log('Default pipeline changed');
            }
        });
    }
}
