<?php

declare(strict_types=1);

namespace App\Domain\Setup\Actions;

use App\Domain\Setup\Enums\SetupStep;
use App\Domain\Setup\Enums\StepStatus;
use App\Domain\Setup\Support\Settings;
use App\Domain\Setup\Support\SetupState;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

/**
 * Opens Mawred to everyone. Required steps must be completed; skipped ones stay revisitable
 * from Settings.
 */
final readonly class FinishSetup
{
    public function __construct(private SetupState $state, private Settings $settings) {}

    public function handle(Actor $actor): void
    {
        $missing = array_values(array_filter(
            SetupStep::cases(),
            fn (SetupStep $step): bool => $step->isRequired() && $this->state->status($step) !== StepStatus::Completed,
        ));

        if ($missing !== []) {
            throw new DomainRuleViolation('setup.incomplete', __('Complete the required steps first.'), [
                'steps' => array_map(fn (SetupStep $step): string => $step->value, $missing),
            ]);
        }

        $this->state->mark(SetupStep::Review, StepStatus::Completed);
        $this->settings->set('setup.completed_at', now()->toIso8601String());

        activity('setup')->causedBy($actor->user)->event('finished')->log('Setup finished');
    }
}
