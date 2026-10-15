<?php

declare(strict_types=1);

namespace App\Domain\Leave\Support;

use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Leave\Data\LeaveBalanceData;
use App\Domain\Leave\Data\LedgerEntryData;
use App\Domain\Leave\Enums\LeaveRequestKind;
use App\Domain\Leave\Enums\LeaveRequestStatus;
use App\Domain\Leave\Enums\LedgerKind;
use App\Domain\Leave\Models\LeaveLedgerEntry;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Data\Values\LeaveRuleValue;
use App\Domain\Policies\Keys\LeaveRuleKey;
use App\Domain\Policies\Models\LeaveType;
use App\Domain\Policies\Support\Calculators\AccrualCalculator;
use App\Domain\Policies\Support\PolicyResolver;
use Illuminate\Support\Collection;

/**
 * Leave balances from the ledger (D63–D65). The ledger is the record; pending requests are held
 * on top of it; accrual not yet posted (the nightly run lags by up to a day, and future months
 * are not posted at all) is projected when checking whether a request fits.
 */
final readonly class LeaveBalances
{
    public function __construct(private PolicyResolver $resolver, private EmployeeDays $days) {}

    public function rule(Employee $employee, LeaveType $type, string $date): LeaveRuleValue
    {
        $rule = $this->resolver->value($employee, $date, LeaveRuleKey::PREFIX.$type->code);

        return $rule instanceof LeaveRuleValue ? $rule : new LeaveRuleValue(0);
    }

    /**
     * Balances for every leave type the employee can take, plus any type they hold days in.
     *
     * @return list<LeaveBalanceData>
     */
    public function all(Employee $employee, int $year): array
    {
        $posted = $this->postedByType($employee, $year);
        $reserved = $this->reservedByType($employee, $year);

        return array_values(LeaveType::withTrashed()
            ->where(fn ($q) => $q->where('is_active', true)->whereNull('deleted_at')
                ->where(fn ($q) => $q->whereNull('eligible_gender')->orWhere('eligible_gender', $employee->gender ?? '')))
            ->orWhereIn('id', array_keys($posted))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (LeaveType $type): LeaveBalanceData => $this->build($employee, $type, $year, $posted[$type->id] ?? [], $reserved[$type->id] ?? 0.0))
            ->all());
    }

    public function of(Employee $employee, LeaveType $type, int $year, ?int $exceptRequestId = null): LeaveBalanceData
    {
        return $this->build($employee, $type, $year, $this->postedByType($employee, $year, $type->id)[$type->id] ?? [], $this->reservedByType($employee, $year, $type->id, $exceptRequestId)[$type->id] ?? 0.0);
    }

    /**
     * What can be requested for leave starting on a date: the ledger, plus accrual earned by then
     * but not yet posted, minus carry-over expired by then, minus other pending requests.
     */
    public function availableOn(Employee $employee, LeaveType $type, string $date, ?int $exceptRequestId = null): float
    {
        $year = LeaveYear::of($date);
        $balance = $this->of($employee, $type, $year, $exceptRequestId);
        $posted = $this->postedByType($employee, $year, $type->id)[$type->id] ?? [];
        $rule = $this->rule($employee, $type, $date);
        $earned = AccrualCalculator::accrued($rule, $employee->hire_date->toDateString(), LeaveYear::start($year), $date, $employee->termination_date?->toDateString())->accrued;
        $unposted = max(0.0, $earned - ($posted[LedgerKind::Accrual->value] ?? 0.0));
        $total = $balance->balance + $unposted;

        if ($rule->maxBalance !== null && $unposted > 0) {
            $total = min($total, max($rule->maxBalance, $balance->balance));
        }

        if ($balance->expiresOn !== null && $balance->expiresOn < $date) {
            $total -= $balance->expiring ?? 0.0;
        }

        return round($total - $balance->reserved, 2);
    }

    /**
     * Unused carry-over that lapses on its expiry date. Leave taken comes out of carried-over days
     * first, so only what was not used by then expires.
     *
     * @return array{amount: float, expiresOn: string}|null
     */
    public function expiringCarry(Employee $employee, LeaveType $type, int $year): ?array
    {
        $entries = LeaveLedgerEntry::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->where('year', $year)
            ->get(['kind', 'amount', 'effective_on', 'expires_on']);

        $carry = $entries->first(fn (LeaveLedgerEntry $e): bool => $e->kind === LedgerKind::CarryOver && $e->expires_on !== null);

        if (! $carry instanceof LeaveLedgerEntry || $carry->expires_on === null || $entries->contains(fn (LeaveLedgerEntry $e): bool => $e->kind === LedgerKind::CarryExpiry)) {
            return null;
        }

        $expiresOn = $carry->expires_on->toDateString();
        $used = 0.0;
        $balance = 0.0;

        foreach ($entries as $entry) {
            $balance += $entry->amount;

            if (in_array($entry->kind, [LedgerKind::Debit, LedgerKind::Credit], true) && $entry->effective_on->toDateString() <= $expiresOn) {
                $used -= $entry->amount;
            }
        }

        $amount = round(max(0.0, min($carry->amount - max(0.0, $used), $balance)), 2);

        return $amount > 0 ? ['amount' => $amount, 'expiresOn' => $expiresOn] : null;
    }

    /**
     * The statement: every ledger line for the year, oldest first, with a running balance.
     *
     * @return list<LedgerEntryData>
     */
    public function statement(Employee $employee, LeaveType $type, int $year): array
    {
        $running = 0.0;

        return array_values(LeaveLedgerEntry::query()
            ->with(['leaveRequest.request', 'actor'])
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->where('year', $year)
            ->orderBy('effective_on')
            ->orderBy('id')
            ->get()
            ->map(function (LeaveLedgerEntry $entry) use (&$running): LedgerEntryData {
                $running = round($running + $entry->amount, 2);
                $request = $entry->leaveRequest?->request;

                return new LedgerEntryData(
                    id: $entry->ulid,
                    kind: $entry->kind->value,
                    label: $entry->kind->label(),
                    amount: $entry->amount,
                    runningBalance: $running,
                    effectiveOn: $entry->effective_on->toDateString(),
                    expiresOn: $entry->expires_on?->toDateString(),
                    note: $entry->note,
                    requestId: $request?->ulid,
                    requestNumber: $request?->number,
                    actor: $entry->actor?->name,
                );
            })
            ->all());
    }

    /**
     * Available days per leave type code, for form rules (`balance('ANNUAL')`).
     *
     * @return array<string, float>
     */
    public function forRules(Employee $employee, int $year): array
    {
        $codes = [];

        foreach ($this->all($employee, $year) as $balance) {
            $codes[$balance->code] = $balance->available;
        }

        return $codes;
    }

    /**
     * @param  array<string, float>  $posted  kind => sum
     */
    private function build(Employee $employee, LeaveType $type, int $year, array $posted, float $reserved): LeaveBalanceData
    {
        $sum = fn (LedgerKind ...$kinds): float => round(array_sum(array_map(fn (LedgerKind $kind): float => $posted[$kind->value] ?? 0.0, $kinds)), 2);
        $balance = round(array_sum($posted), 2);
        $date = max(LeaveYear::start($year), min(LeaveYear::end($year), $this->days->today($employee)));
        $expiring = $this->expiringCarry($employee, $type, $year);

        return new LeaveBalanceData(
            typeId: $type->ulid,
            code: $type->code,
            name: FieldSpec::string($type->getTranslation('name', app()->getLocale())),
            unit: $type->unit,
            year: $year,
            entitlement: $this->rule($employee, $type, $date)->entitlementAfter((int) $employee->hire_date->diffInYears($date, true)),
            earned: $sum(LedgerKind::Accrual, LedgerKind::Cap),
            carriedOver: $sum(LedgerKind::CarryOver, LedgerKind::CarryExpiry),
            adjusted: $sum(LedgerKind::Adjustment),
            taken: -1 * $sum(LedgerKind::Debit, LedgerKind::Credit),
            reserved: round($reserved, 2),
            balance: $balance,
            available: round($balance - $reserved, 2),
            expiring: $expiring['amount'] ?? null,
            expiresOn: $expiring['expiresOn'] ?? null,
        );
    }

    /**
     * @return array<int, array<string, float>> leave type id => kind => sum
     */
    private function postedByType(Employee $employee, int $year, ?int $typeId = null): array
    {
        $posted = [];

        /** @var Collection<int, LeaveLedgerEntry> $rows */
        $rows = LeaveLedgerEntry::query()
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->when($typeId !== null, fn ($q) => $q->where('leave_type_id', $typeId))
            ->groupBy('leave_type_id', 'kind')
            ->selectRaw('leave_type_id, kind, sum(amount) as amount')
            ->get();

        foreach ($rows as $row) {
            $posted[$row->leave_type_id][$row->kind->value] = $row->amount;
        }

        return $posted;
    }

    /**
     * @return array<int, float> leave type id => days held by pending requests
     */
    private function reservedByType(Employee $employee, int $year, ?int $typeId = null, ?int $exceptRequestId = null): array
    {
        $reserved = [];

        $rows = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('kind', LeaveRequestKind::Leave->value)
            ->where('status', LeaveRequestStatus::Pending->value)
            ->whereBetween('starts_on', [LeaveYear::start($year), LeaveYear::end($year)])
            ->when($typeId !== null, fn ($q) => $q->where('leave_type_id', $typeId))
            ->when($exceptRequestId !== null, fn ($q) => $q->whereKeyNot($exceptRequestId))
            ->groupBy('leave_type_id')
            ->selectRaw('leave_type_id, sum(amount) as amount')
            ->get();

        foreach ($rows as $row) {
            $reserved[(int) $row->leave_type_id] = (float) $row->amount;
        }

        return $reserved;
    }
}
