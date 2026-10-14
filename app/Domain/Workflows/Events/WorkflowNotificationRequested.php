<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Events;

use App\Domain\Workflows\Models\WorkflowStep;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A Notify node fired. The notifications domain renders and delivers it (the step ULID is the
 * idempotency key, so a retried step never notifies twice).
 *
 * @param  list<int>  $userIds
 * @param  array<string, string>  $subject
 * @param  array<string, string>  $body
 */
final readonly class WorkflowNotificationRequested implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<int>  $userIds
     * @param  array<string, string>  $subject
     * @param  array<string, string>  $body
     */
    public function __construct(public WorkflowStep $step, public string $channel, public array $userIds, public array $subject, public array $body) {}
}
