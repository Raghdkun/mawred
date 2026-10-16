<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Hiring\Enums\RequisitionStatus;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Requests\Enums\RequestStatus;
use App\Domain\Requests\Models\EmployeeRequest;

/**
 * Ends a pending requisition whose request was rejected or withdrawn. Approval is handled by the
 * workflow's Update Record step (`OpenRequisition`).
 */
final readonly class SettleRequisition
{
    public function handle(EmployeeRequest $request, RequestStatus $status): void
    {
        $to = match ($status) {
            RequestStatus::Rejected => RequisitionStatus::Rejected,
            RequestStatus::Cancelled => RequisitionStatus::Cancelled,
            default => null,
        };

        if ($to === null) {
            return;
        }

        $requisition = JobRequisition::query()->where('request_id', $request->id)->where('status', RequisitionStatus::Pending)->first();

        if ($requisition instanceof JobRequisition) {
            $requisition->forceFill(['status' => $to, 'closed_at' => now()])->save();
            activity('hiring')->performedOn($requisition)->event('status_changed')
                ->withProperties(['from' => RequisitionStatus::Pending->value, 'to' => $to->value])->log('Requisition status changed');
        }
    }
}
