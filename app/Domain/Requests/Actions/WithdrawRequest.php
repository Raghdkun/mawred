<?php

declare(strict_types=1);

namespace App\Domain\Requests\Actions;

use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Models\WorkflowInstance;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

/** The requester withdraws an open request (handoff 12, destructive with confirmation). */
final readonly class WithdrawRequest
{
    public function __construct(private WorkflowEngine $engine) {}

    public function handle(Actor $actor, EmployeeRequest $request, ?string $reason = null): void
    {
        $instance = $request->instance()->first();

        if (! $request->status->isOpen() || ! $instance instanceof WorkflowInstance) {
            throw new DomainRuleViolation('requests.closed', __('This request has already been decided.'));
        }

        $this->engine->cancel($instance, $actor->user, $reason);

        activity('requests')->causedBy($actor->user)->performedOn($request)->event('withdrawn')->log('Request withdrawn');
    }
}
