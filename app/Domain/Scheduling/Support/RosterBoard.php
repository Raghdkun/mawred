<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Support;

use App\Domain\Access\Models\User;
use App\Domain\Leave\Enums\LeaveRequestKind;
use App\Domain\Leave\Enums\LeaveRequestStatus;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Data\Values\RestValue;
use App\Domain\Policies\Support\BranchHolidays;
use App\Domain\Policies\Support\HolidayOccurrence;
use App\Domain\Policies\Support\PolicyResolver;
use App\Domain\Scheduling\Data\RosterShiftData;
use App\Domain\Scheduling\Data\ShiftTemplateData;
use App\Domain\Scheduling\Enums\ClaimStatus;
use App\Domain\Scheduling\Enums\ShiftStatus;
use App\Domain\Scheduling\Models\CoverageTarget;
use App\Domain\Scheduling\Models\OpenShiftClaim;
use App\Domain\Scheduling\Models\RosterShift;
use App\Domain\Scheduling\Models\RotationPattern;
use App\Domain\Scheduling\Models\ShiftTemplate;
use App\Domain\Scheduling\Queries\RosterPeopleQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * The roster planner's payload (handoff 22, architecture: one query per roster range, a flat
 * normalised payload): people, shifts keyed by person and date with their conflicts, open
 * shifts, time off, holidays, coverage targets, templates and rotations.
 */
final readonly class RosterBoard
{
    public function __construct(
        private RosterPeopleQuery $people,
        private RosterAccess $access,
        private BranchHolidays $holidays,
        private PolicyResolver $resolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $user, Branch $branch, string $from, string $to): array
    {
        $employees = $this->people->in($branch->id, $from)->visibleTo($user)->with('currentPosition')->limit(500)->get();
        $ids = $employees->pluck('id')->all();
        $shifts = RosterShift::query()
            ->with(['employee', 'template', 'position'])
            ->withCount(['claims' => fn (Builder $q) => $q->where('status', 'pending')])
            ->where('branch_id', $branch->id)
            ->whereBetween('date', [$from, $to])
            ->where(fn (Builder $q) => $q->whereNull('employee_id')->orWhereIn('employee_id', $ids))
            ->orderBy('date')
            ->oldest('starts_at')
            ->get();
        $off = LeaveRequest::query()
            ->whereIn('employee_id', $ids)
            ->whereIn('status', [LeaveRequestStatus::Pending->value, LeaveRequestStatus::Approved->value])
            ->where('kind', '!=', LeaveRequestKind::Hourly->value)
            ->where('starts_on', '<=', $to)
            ->where('ends_on', '>=', $from)
            ->get();
        $offDates = [];

        foreach ($off as $leave) {
            for ($day = $leave->starts_on; $day->lessThanOrEqualTo($leave->ends_on); $day = $day->addDay()) {
                $offDates[$leave->employee_id][] = $day->toDateString();
            }
        }

        $conflicts = [];

        foreach ($shifts->whereNotNull('employee_id')->groupBy('employee_id') as $employeeId => $personShifts) {
            $employee = $employees->firstWhere('id', $employeeId);
            $rest = $employee instanceof Employee ? $this->resolver->value($employee, $from, 'scheduling.rest') : null;
            $conflicts += RosterConflicts::find(
                array_values($personShifts->map(fn (RosterShift $s): array => ['id' => $s->ulid, 'date' => $s->date->toDateString(), 'start' => $s->span()[0], 'end' => $s->span()[1], 'paid' => $s->paidMinutes()])->all()),
                $offDates[$employeeId] ?? [],
                $rest instanceof RestValue ? $rest : new RestValue,
            );
        }

        $byUlid = $employees->keyBy('id');

        return [
            'branch' => ['id' => $branch->ulid, 'name' => $branch->getTranslations('name'), 'weekend' => array_map(intval(...), $branch->weekend_days)],
            'from' => $from,
            'to' => $to,
            'people' => array_values($employees->map(fn (Employee $e): array => [
                'id' => $e->ulid,
                'number' => $e->employee_number,
                'nameEn' => $e->name_en,
                'nameAr' => $e->name_ar,
                'preferredName' => $e->preferred_name,
                'position' => $e->currentPosition?->getTranslations('title'),
                'canEdit' => $this->access->canManageEmployee($user, $e),
            ])->all()),
            'shifts' => array_values($shifts->map(fn (RosterShift $s): RosterShiftData => RosterShiftData::fromModel($s, $conflicts[$s->ulid] ?? []))->all()),
            'timeOff' => array_values($off->map(fn (LeaveRequest $l): array => [
                'employeeId' => $byUlid->get($l->employee_id)?->ulid,
                'from' => $l->starts_on->toDateString(),
                'to' => $l->ends_on->toDateString(),
                'status' => $l->status->value,
            ])->all()),
            'holidays' => array_map(fn (HolidayOccurrence $o): array => ['date' => $o->observedOn, 'name' => $o->name], $this->holidays->between($branch, $from, $to)),
            'coverage' => CoverageTarget::query()->where('branch_id', $branch->id)->pluck('required', 'weekday')->all(),
            'templates' => array_values(ShiftTemplate::query()->where('is_active', true)->where(fn (Builder $q) => $q->whereNull('branch_id')->orWhere('branch_id', $branch->id))->oldest('starts_at')->get()->map(ShiftTemplateData::fromModel(...))->all()),
            'rotations' => array_values(RotationPattern::query()->where(fn (Builder $q) => $q->whereNull('branch_id')->orWhere('branch_id', $branch->id))->get()->map(fn (RotationPattern $p): array => ['id' => $p->ulid, 'name' => $p->getTranslations('name'), 'length' => count($p->days)])->all()),
            'drafts' => $shifts->where('status', ShiftStatus::Draft)->count(),
            'claims' => $this->claims($shifts->whereNull('employee_id')),
        ];
    }

    /**
     * Pending claims on open shifts, by shift.
     *
     * @param  Collection<int, RosterShift>  $openShifts
     * @return array<string, list<array{id: string, employeeId: string, nameEn: string, nameAr: string|null}>>
     */
    private function claims(Collection $openShifts): array
    {
        $ulids = $openShifts->pluck('ulid', 'id');
        $claims = [];

        foreach (OpenShiftClaim::query()->with('employee')->whereIn('roster_shift_id', $ulids->keys())->where('status', ClaimStatus::Pending->value)->oldest()->get() as $claim) {
            $employee = $claim->employee;
            $shift = $ulids->get($claim->roster_shift_id);

            if ($employee instanceof Employee && is_string($shift)) {
                $claims[$shift][] = ['id' => $claim->ulid, 'employeeId' => $employee->ulid, 'nameEn' => $employee->name_en, 'nameAr' => $employee->name_ar];
            }
        }

        return $claims;
    }

    /**
     * The planner's date range: a week (from the branch's first working day), two weeks or a
     * calendar month.
     *
     * @return array{0: string, 1: string}
     */
    public static function range(string $view, string $anchor, Branch $branch): array
    {
        $date = CarbonImmutable::parse($anchor);

        if ($view === 'month') {
            return [$date->startOfMonth()->toDateString(), $date->endOfMonth()->toDateString()];
        }

        $weekend = array_map(intval(...), $branch->weekend_days);
        $firstDay = $weekend === [] ? 1 : (max($weekend) % 7) + 1;
        $start = $date->subDays(($date->dayOfWeekIso - $firstDay + 7) % 7);

        return [$start->toDateString(), $start->addDays($view === '2weeks' ? 13 : 6)->toDateString()];
    }
}
