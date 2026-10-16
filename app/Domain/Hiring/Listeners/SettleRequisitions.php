<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Listeners;

use App\Domain\Hiring\Actions\SettleRequisition;
use App\Domain\Requests\Enums\RequestStatus;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Workflows\Events\InstanceFinished;

/** Ends pending requisitions when their approval chain rejects or is withdrawn (D84). */
final readonly class SettleRequisitions
{
    public function __construct(private SettleRequisition $settle) {}

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
