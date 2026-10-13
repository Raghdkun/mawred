<?php

declare(strict_types=1);

namespace App\Domain\Setup\Support;

use App\Domain\Setup\Enums\SetupStep;
use App\Domain\Setup\Enums\StepStatus;

/**
 * Resumable wizard state, stored in settings (`setup.steps`, `setup.completed_at`).
 */
final readonly class SetupState
{
    public function __construct(private Settings $settings) {}

    public function isComplete(): bool
    {
        return $this->settings->get('setup.completed_at') !== null;
    }

    public function status(SetupStep $step): StepStatus
    {
        $steps = $this->settings->get('setup.steps', []);
        $value = is_array($steps) ? ($steps[$step->value] ?? null) : null;

        return StepStatus::tryFrom(is_string($value) ? $value : '') ?? StepStatus::Pending;
    }

    /**
     * @return array<string, StepStatus>
     */
    public function all(): array
    {
        $statuses = [];

        foreach (SetupStep::cases() as $step) {
            $statuses[$step->value] = $this->status($step);
        }

        return $statuses;
    }

    /** The first step that is still pending, or Review when everything has been handled. */
    public function current(): SetupStep
    {
        foreach (SetupStep::cases() as $step) {
            if ($step !== SetupStep::Review && $this->status($step) === StepStatus::Pending) {
                return $step;
            }
        }

        return SetupStep::Review;
    }

    public function mark(SetupStep $step, StepStatus $status): void
    {
        $steps = $this->settings->get('setup.steps', []);
        $steps = is_array($steps) ? $steps : [];
        $steps[$step->value] = $status->value;

        $this->settings->set('setup.steps', $steps);
    }
}
