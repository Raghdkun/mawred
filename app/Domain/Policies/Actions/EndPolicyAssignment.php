<?php

declare(strict_types=1);

namespace App\Domain\Policies\Actions;

use App\Domain\Policies\Models\PolicyAssignment;
use App\Domain\Policies\Support\PolicyEpoch;
use App\Support\Errors\DomainRuleViolation;

/**
 * Stops a set applying to a target after a date (less specific levels take over again).
 */
final readonly class EndPolicyAssignment
{
    public function __construct(private PolicyEpoch $epoch) {}

    public function handle(PolicyAssignment $assignment, string $lastDay): void
    {
        if ($lastDay < $assignment->effective_from->toDateString()) {
            $assignment->delete();
        } elseif ($assignment->effective_to !== null && $assignment->effective_to->toDateString() < $lastDay) {
            throw new DomainRuleViolation('policies.already_ended', __('This assignment has already ended.'));
        } else {
            $assignment->forceFill(['effective_to' => $lastDay])->save();
        }

        $this->epoch->bump();
    }
}
