<?php

declare(strict_types=1);

namespace App\Domain\Requests\Listeners;

use App\Domain\Requests\Enums\RequestStatus;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Workflows\Enums\InstanceStatus;
use App\Domain\Workflows\Enums\Outcome;
use App\Domain\Workflows\Events\InstanceFinished;

/** Keeps a request's status in step with its workflow (returned, approved, rejected…). */
final class SyncRequestStatus
{
    public function handle(InstanceFinished $event): void
    {
        $instance = $event->instance;

        if ($instance->subject_type !== EmployeeRequest::class || $instance->subject_id === null) {
            return;
        }

        $status = match (true) {
            $instance->status === InstanceStatus::Returned => RequestStatus::Returned,
            $instance->outcome === Outcome::Approved => RequestStatus::Approved,
            $instance->outcome === Outcome::Rejected => RequestStatus::Rejected,
            $instance->outcome === Outcome::Cancelled => RequestStatus::Cancelled,
            default => RequestStatus::Completed,
        };

        EmployeeRequest::query()->whereKey($instance->subject_id)->update([
            'status' => $status->value,
            'decided_at' => $status->isOpen() ? null : now(),
        ]);
    }
}
