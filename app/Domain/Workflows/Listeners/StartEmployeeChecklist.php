<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Listeners;

use App\Domain\Forms\Support\FormContext;
use App\Domain\People\Events\EmployeeHired;
use App\Domain\People\Events\EmployeeTerminated;
use App\Domain\Workflows\Actions\SeedChecklists;
use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Models\WorkflowDefinition;
use App\Support\Actors\Channel;

/**
 * Starts the onboarding or offboarding checklist for someone hired or leaving by hand. Bulk
 * imports and seeds bring in existing staff, so they don't get one.
 */
final readonly class StartEmployeeChecklist
{
    public function __construct(private WorkflowEngine $engine, private FormContext $context) {}

    public function handle(EmployeeHired|EmployeeTerminated $event): void
    {
        if (! $event->actor->isHuman() || $event->actor->channel === Channel::Import) {
            return;
        }

        $code = $event instanceof EmployeeHired ? SeedChecklists::ONBOARDING : SeedChecklists::OFFBOARDING;
        $definition = WorkflowDefinition::query()->where('code', $code)->whereNotNull('published_version_id')->first();

        if (! $definition instanceof WorkflowDefinition) {
            return;
        }

        $this->engine->start($definition, ['field' => [], ...$this->context->forEmployee($event->employee)], null, $event->employee, $event->employee);
    }
}
