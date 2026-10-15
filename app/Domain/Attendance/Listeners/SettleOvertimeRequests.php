<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Listeners;

use App\Domain\Attendance\Actions\SettleOvertime;
use App\Domain\Requests\Enums\RequestStatus;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Requests\Models\RequestType;
use App\Domain\Workflows\Events\InstanceFinished;

/** Settles overtime when an Overtime request's workflow finishes. */
final readonly class SettleOvertimeRequests
{
    public function __construct(private SettleOvertime $settle) {}

    public function handle(InstanceFinished $event): void
    {
        $instance = $event->instance;

        if ($instance->subject_type !== EmployeeRequest::class || $instance->subject_id === null) {
            return;
        }

        $request = EmployeeRequest::query()->find($instance->subject_id);

        if ($request instanceof EmployeeRequest && RequestType::withTrashed()->whereKey($request->request_type_id)->value('code') === 'OVERTIME') {
            $this->settle->handle($request, RequestStatus::fromInstance($instance));
        }
    }
}
