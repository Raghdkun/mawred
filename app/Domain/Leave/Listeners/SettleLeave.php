<?php

declare(strict_types=1);

namespace App\Domain\Leave\Listeners;

use App\Domain\Leave\Actions\SettleLeaveRequest;
use App\Domain\Requests\Enums\RequestStatus;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Workflows\Events\InstanceFinished;

/** Settles a time-away request's hold when its workflow decides it (D64). */
final readonly class SettleLeave
{
    public function __construct(private SettleLeaveRequest $settle) {}

    public function handle(InstanceFinished $event): void
    {
        $instance = $event->instance;

        if ($instance->subject_type !== EmployeeRequest::class || $instance->subject_id === null) {
            return;
        }

        $request = EmployeeRequest::query()->find($instance->subject_id);

        if ($request instanceof EmployeeRequest) {
            $this->settle->handle($request, RequestStatus::fromInstance($instance));
        }
    }
}
