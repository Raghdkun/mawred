<?php

declare(strict_types=1);

namespace App\Domain\Leave\Support;

use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Leave\Data\LeaveQuoteData;
use App\Domain\Leave\Enums\LeaveRequestKind;
use App\Domain\Leave\Enums\LeaveRequestStatus;
use App\Domain\Leave\Models\LeaveBlackout;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Organization\Models\Department;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Data\Values\HourlyPermissionValue;
use App\Domain\Policies\Models\LeaveType;
use App\Domain\Policies\Support\PolicyResolver;
use App\Domain\Requests\Models\RequestType;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * Reads a time-away request's answers and checks it before it is sent or held: working days,
 * the balance (overdraw is blocked, D65), overlapping requests, blackout periods, the leave year
 * boundary, and the hourly-permission quota. The request form shows the same quote live.
 */
final readonly class LeaveQuotes
{
    public function __construct(
        private LeaveBalances $balances,
        private EmployeeDays $days,
        private PolicyResolver $resolver,
    ) {}

    /**
     * @param  array<string, mixed>  $answers
     */
    public function quote(Employee $employee, RequestType $type, array $answers, ?int $exceptRequestId = null): ?LeaveQuoteData
    {
        $kind = $this->kindOf($type, $answers);

        return match ($kind) {
            LeaveRequestKind::Leave => $this->leave($employee, $type, $answers, $exceptRequestId),
            LeaveRequestKind::DayOff => $this->dayOff($employee, $answers, $exceptRequestId),
            LeaveRequestKind::Hourly => $this->hourly($employee, $answers, $exceptRequestId),
            null => null,
        };
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    public function kindOf(RequestType $type, array $answers): ?LeaveRequestKind
    {
        return match ($type->behaviour) {
            'leave' => LeaveRequestKind::Leave,
            'hourly_permission' => ($answers['kind'] ?? null) === 'hourly' ? LeaveRequestKind::Hourly : LeaveRequestKind::DayOff,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function leave(Employee $employee, RequestType $type, array $answers, ?int $exceptRequestId): LeaveQuoteData
    {
        $leaveType = $type->leave_type_id === null ? null : LeaveType::withTrashed()->find($type->leave_type_id);
        [$from, $to] = $this->range($answers['dates'] ?? null);
        $halfDay = in_array($answers['half_day'] ?? null, ['morning', 'afternoon'], true) ? FieldSpec::string($answers['half_day']) : null;
        $problems = [];

        if (! $leaveType instanceof LeaveType) {
            return $this->empty(LeaveRequestKind::Leave, null, 'days', [$this->problem('leave.no_type', __('This request type is not linked to a leave type. Ask HR to set it up.'))]);
        }

        $unit = $leaveType->unit === 'hours' ? 'hours' : 'days';
        $name = FieldSpec::string($leaveType->getTranslation('name', app()->getLocale()));

        if ($from === null || $to === null) {
            return $this->empty(LeaveRequestKind::Leave, $name, $unit, []);
        }

        $dates = $this->days->workingDates($employee, $from, $to);
        $amount = (float) count($dates);

        if ($halfDay !== null && $from === $to && $leaveType->allows_half_days && $amount > 0) {
            $amount = 0.5;
        }

        if ($unit === 'hours') {
            $amount = round($amount * $this->days->hoursPerDay($employee, $from), 2);
        }

        if (LeaveYear::of($from) !== LeaveYear::of($to)) {
            $problems[] = $this->problem('leave.spans_years', __('Leave can’t run into the next year. Send one request up to 31 December and another from 1 January.'));
        }

        if ($amount <= 0) {
            $problems[] = $this->problem('leave.no_working_days', __('These dates are all weekends or holidays, so there is nothing to take.'));
        }

        if (! $leaveType->is_active || $leaveType->trashed() || ($leaveType->eligible_gender !== null && $leaveType->eligible_gender !== $employee->gender)) {
            $problems[] = $this->problem('leave.not_eligible', __('You can’t take this type of leave.'));
        }

        $problems = [...$problems, ...$this->overlaps($employee, $from, $to, $exceptRequestId), ...$this->blackouts($employee, $leaveType, $from, $to)];

        $available = $this->balances->availableOn($employee, $leaveType, $from, $exceptRequestId);
        $after = round($available - $amount, 2);

        if ($amount > 0 && $after < 0) {
            $problems[] = $this->problem('leave.insufficient', __('You don’t have enough balance: :available available, :requested requested.', [
                'available' => $this->number($available),
                'requested' => $this->number($amount),
            ]));
        }

        return new LeaveQuoteData(
            kind: LeaveRequestKind::Leave->value,
            typeName: $name,
            unit: $unit,
            from: $from,
            to: $to,
            amount: $amount,
            dates: $dates,
            available: $available,
            after: $after,
            attachmentRequired: $this->attachmentRequired($employee, $leaveType, $from, $amount),
            problems: $problems,
        );
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function dayOff(Employee $employee, array $answers, ?int $exceptRequestId): LeaveQuoteData
    {
        $date = $this->date($answers['date'] ?? null);

        if ($date === null) {
            return $this->empty(LeaveRequestKind::DayOff, null, 'days', []);
        }

        $dates = $this->days->workingDates($employee, $date, $date);
        $problems = $this->overlaps($employee, $date, $date, $exceptRequestId);

        if ($dates === []) {
            $problems[] = $this->problem('leave.no_working_days', __('That day is a weekend or a holiday already.'));
        }

        return new LeaveQuoteData(LeaveRequestKind::DayOff->value, null, 'days', $date, $date, (float) count($dates), $dates, null, null, false, $problems);
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function hourly(Employee $employee, array $answers, ?int $exceptRequestId): LeaveQuoteData
    {
        $date = $this->date($answers['date'] ?? null);
        $fromTime = $this->time($answers['from_time'] ?? null);
        $toTime = $this->time($answers['to_time'] ?? null);

        if ($date === null || $fromTime === null || $toTime === null) {
            return $this->empty(LeaveRequestKind::Hourly, null, 'minutes', []);
        }

        $minutes = (int) CarbonImmutable::parse($date.' '.$fromTime)->diffInMinutes(CarbonImmutable::parse($date.' '.$toTime), false);
        $quota = $this->resolver->value($employee, $date, 'leave.hourly_permissions');
        $quota = $quota instanceof HourlyPermissionValue ? $quota : new HourlyPermissionValue;
        $month = CarbonImmutable::parse($date);
        $used = (int) LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('kind', LeaveRequestKind::Hourly->value)
            ->whereIn('status', [LeaveRequestStatus::Pending->value, LeaveRequestStatus::Approved->value])
            ->whereBetween('starts_on', [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()])
            ->when($exceptRequestId !== null, fn ($q) => $q->whereKeyNot($exceptRequestId))
            ->sum('minutes');
        $available = (float) max(0, $quota->minutesPerMonth - $used);
        $problems = [];

        if ($minutes <= 0) {
            $problems[] = $this->problem('leave.bad_times', __('The end time must be after the start time.'));
        } elseif ($minutes > $quota->maxMinutesPerRequest) {
            $problems[] = $this->problem('leave.too_long', __('One permission can be at most :minutes minutes.', ['minutes' => $this->number($quota->maxMinutesPerRequest)]));
        }

        if ($minutes > 0 && $minutes > $available) {
            $problems[] = $this->problem('leave.quota', __('You have :minutes minutes of permission left this month.', ['minutes' => $this->number($available)]));
        }

        if ($this->days->workingDates($employee, $date, $date) === []) {
            $problems[] = $this->problem('leave.no_working_days', __('That day is a weekend or a holiday already.'));
        }

        return new LeaveQuoteData(LeaveRequestKind::Hourly->value, null, 'minutes', $date, $date, (float) max(0, $minutes), [$date], $available, round($available - max(0, $minutes), 2), false, $problems);
    }

    /**
     * @return list<array{code: string, message: string}>
     */
    private function overlaps(Employee $employee, string $from, string $to, ?int $exceptRequestId): array
    {
        $clash = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [LeaveRequestStatus::Pending->value, LeaveRequestStatus::Approved->value])
            ->where('starts_on', '<=', $to)
            ->where('ends_on', '>=', $from)
            ->when($exceptRequestId !== null, fn ($q) => $q->whereKeyNot($exceptRequestId))
            ->orderBy('starts_on')
            ->first();

        return $clash instanceof LeaveRequest
            ? [$this->problem('leave.overlap', __('You already have time off from :from to :to.', ['from' => $clash->starts_on->toDateString(), 'to' => $clash->ends_on->toDateString()]))]
            : [];
    }

    /**
     * @return list<array{code: string, message: string}>
     */
    private function blackouts(Employee $employee, LeaveType $type, string $from, string $to): array
    {
        $departments = [];

        if ($employee->current_department_id !== null) {
            $path = Department::withTrashed()->whereKey($employee->current_department_id)->value('path');
            $departments = array_values(array_map(intval(...), array_filter(explode('.', is_string($path) ? $path : (string) $employee->current_department_id), fn (string $id): bool => $id !== '')));
        }

        $blackout = LeaveBlackout::query()
            ->where('starts_on', '<=', $to)
            ->where('ends_on', '>=', $from)
            ->where(fn (Builder $q) => $q->whereNull('branch_id')->orWhere('branch_id', $employee->current_branch_id))
            ->where(fn (Builder $q) => $q->whereNull('department_id')->orWhereIn('department_id', $departments))
            ->get()
            ->first(fn (LeaveBlackout $b): bool => $b->leave_type_ids === null || $b->leave_type_ids === [] || in_array($type->id, array_map(intval(...), $b->leave_type_ids), true));

        return $blackout instanceof LeaveBlackout
            ? [$this->problem('leave.blackout', __('Leave can’t be taken from :from to :to (:reason).', [
                'from' => $blackout->starts_on->toDateString(),
                'to' => $blackout->ends_on->toDateString(),
                'reason' => FieldSpec::string($blackout->getTranslation('name', app()->getLocale())),
            ]))]
            : [];
    }

    private function attachmentRequired(Employee $employee, LeaveType $type, string $from, float $amount): bool
    {
        if (! $type->requires_attachment) {
            return false;
        }

        $after = $this->balances->rule($employee, $type, $from)->attachmentAfterDays;

        return $after === null || $amount > $after;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function range(mixed $value): array
    {
        if (! is_array($value)) {
            return [null, null];
        }

        $from = $this->date($value['from'] ?? null);
        $to = $this->date($value['to'] ?? null) ?? $from;

        if ($from === null || $to === null || $to < $from) {
            return [null, null];
        }

        return [$from, $to];
    }

    private function date(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    private function time(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{2}:\d{2}$/', $value) === 1 ? $value : null;
    }

    private function number(float|int $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    /**
     * @return array{code: string, message: string}
     */
    private function problem(string $code, string $message): array
    {
        return ['code' => $code, 'message' => $message];
    }

    /**
     * @param  list<array{code: string, message: string}>  $problems
     */
    private function empty(LeaveRequestKind $kind, ?string $name, string $unit, array $problems): LeaveQuoteData
    {
        return new LeaveQuoteData($kind->value, $name, $unit, null, null, 0.0, [], null, null, false, $problems);
    }
}
