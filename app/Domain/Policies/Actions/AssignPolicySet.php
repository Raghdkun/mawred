<?php

declare(strict_types=1);

namespace App\Domain\Policies\Actions;

use App\Domain\Policies\Enums\PolicyLevel;
use App\Domain\Policies\Models\PolicyAssignment;
use App\Domain\Policies\Models\PolicySet;
use App\Domain\Policies\Support\PolicyEpoch;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;

/**
 * Attaches a set to a level/target from a date (screen 18). The set currently attached there
 * ends the day before; one set per target at a time (exclusion constraint).
 */
final readonly class AssignPolicySet
{
    public function __construct(private ConnectionInterface $db, private PolicyEpoch $epoch) {}

    public function handle(Actor $actor, PolicySet $set, PolicyLevel $level, ?int $targetId, string $effectiveFrom, ?string $reason = null): PolicyAssignment
    {
        if ($level->needsTarget() !== ($targetId !== null)) {
            throw new DomainRuleViolation('policies.target_required', __('Choose who this applies to.'), ['targetId' => [__('Choose who this applies to.')]]);
        }

        if ($effectiveFrom < CarbonImmutable::now('UTC')->toDateString() && ($reason === null || trim($reason) === '')) {
            throw new DomainRuleViolation('policies.backdate_reason', __('Changes that start in the past need a reason.'), ['reason' => [__('Changes that start in the past need a reason.')]]);
        }

        $assignment = $this->db->transaction(function () use ($actor, $set, $level, $targetId, $effectiveFrom, $reason): PolicyAssignment {
            $existing = PolicyAssignment::query()
                ->where('level', $level->value)
                ->when($targetId === null, fn ($q) => $q->whereNull('target_id'), fn ($q) => $q->where('target_id', $targetId))
                ->lockForUpdate()
                ->get();

            if ($existing->contains(fn (PolicyAssignment $a): bool => $a->effective_from->toDateString() > $effectiveFrom)) {
                throw new DomainRuleViolation('policies.later_assignment_exists', __('A later assignment is already scheduled here. Edit that one instead.'));
            }

            $open = $existing->first(fn (PolicyAssignment $a): bool => $a->coversDate($effectiveFrom));

            if ($open !== null) {
                if ($open->effective_from->toDateString() === $effectiveFrom) {
                    $open->delete();
                } else {
                    $open->forceFill(['effective_to' => CarbonImmutable::parse($effectiveFrom)->subDay()->toDateString()])->save();
                }
            }

            return PolicyAssignment::query()->create([
                'policy_set_id' => $set->id,
                'level' => $level,
                'target_id' => $targetId,
                'effective_from' => $effectiveFrom,
                'reason' => $reason,
                'created_by' => $actor->user?->id,
            ]);
        });

        $this->epoch->bump();

        return $assignment;
    }
}
