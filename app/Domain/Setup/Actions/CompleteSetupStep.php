<?php

declare(strict_types=1);

namespace App\Domain\Setup\Actions;

use App\Domain\Setup\Enums\SetupStep;
use App\Domain\Setup\Enums\StepStatus;
use App\Domain\Setup\Support\SetupState;
use App\Support\Actors\Actor;

final readonly class CompleteSetupStep
{
    public function __construct(private SetupState $state) {}

    public function handle(Actor $actor, SetupStep $step): void
    {
        $this->state->mark($step, StepStatus::Completed);

        activity('setup')->causedBy($actor->user)->event('step.completed')
            ->withProperties(['step' => $step->value])->log('Setup step completed');
    }
}
