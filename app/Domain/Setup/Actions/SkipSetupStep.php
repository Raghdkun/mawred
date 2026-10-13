<?php

declare(strict_types=1);

namespace App\Domain\Setup\Actions;

use App\Domain\Setup\Enums\SetupStep;
use App\Domain\Setup\Enums\StepStatus;
use App\Domain\Setup\Support\SetupState;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

final readonly class SkipSetupStep
{
    public function __construct(private SetupState $state) {}

    public function handle(Actor $actor, SetupStep $step): void
    {
        if ($step->isRequired()) {
            throw new DomainRuleViolation('setup.step_required', __('This step is required and cannot be skipped.'));
        }

        $this->state->mark($step, StepStatus::Skipped);

        activity('setup')->causedBy($actor->user)->event('step.skipped')
            ->withProperties(['step' => $step->value])->log('Setup step skipped');
    }
}
