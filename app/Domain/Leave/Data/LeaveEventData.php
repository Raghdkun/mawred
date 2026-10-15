<?php

declare(strict_types=1);

namespace App\Domain\Leave\Data;

use App\Domain\Leave\Models\LeaveRequest;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Time away on the calendar or in someone's list. */
#[TypeScript]
final class LeaveEventData extends Data
{
    public function __construct(
        public string $id,
        public string $employeeId,
        public string $employeeName,
        public ?string $employeeNameAr,
        public string $kind,
        public ?string $typeCode,
        public ?string $typeName,
        public string $from,
        public string $to,
        public ?string $halfDay,
        public ?string $fromTime,
        public ?string $toTime,
        public float $amount,
        public ?int $minutes,
        public string $status,
        public ?string $requestId,
    ) {}

    public static function fromModel(LeaveRequest $leave): self
    {
        $type = $leave->leaveType;
        $name = $type?->getTranslation('name', app()->getLocale());

        return new self(
            id: $leave->ulid,
            employeeId: $leave->employee->ulid ?? '',
            employeeName: $leave->employee->name_en ?? '',
            employeeNameAr: $leave->employee?->name_ar,
            kind: $leave->kind->value,
            typeCode: $type?->code,
            typeName: is_string($name) ? $name : null,
            from: $leave->starts_on->toDateString(),
            to: $leave->ends_on->toDateString(),
            halfDay: $leave->half_day,
            fromTime: $leave->from_time === null ? null : substr($leave->from_time, 0, 5),
            toTime: $leave->to_time === null ? null : substr($leave->to_time, 0, 5),
            amount: $leave->amount,
            minutes: $leave->minutes,
            status: $leave->status->value,
            requestId: $leave->request?->ulid,
        );
    }
}
