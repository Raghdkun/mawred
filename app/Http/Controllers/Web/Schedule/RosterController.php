<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedule;

use App\Domain\Access\Models\User;
use App\Domain\Leave\Support\EmployeeDays;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Scheduling\Actions\ApplyRotation;
use App\Domain\Scheduling\Actions\AssignOpenShift;
use App\Domain\Scheduling\Actions\CopyRosterWeek;
use App\Domain\Scheduling\Actions\DeleteRosterShift;
use App\Domain\Scheduling\Actions\PublishRoster;
use App\Domain\Scheduling\Actions\SaveCoverageTargets;
use App\Domain\Scheduling\Actions\SaveRosterShift;
use App\Domain\Scheduling\Data\RosterShiftInputData;
use App\Domain\Scheduling\Models\OpenShiftClaim;
use App\Domain\Scheduling\Models\RosterShift;
use App\Domain\Scheduling\Models\RotationPattern;
use App\Domain\Scheduling\Support\RosterAccess;
use App\Domain\Scheduling\Support\RosterBoard;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Roster planner (handoff 22): a branch's people × days with shifts, open shifts, coverage and
 * the publish bar. Every change is a draft until published.
 */
final class RosterController extends Controller
{
    public function __construct(private readonly RosterAccess $access) {}

    public function show(Request $request, #[CurrentUser] User $user, RosterBoard $board, EmployeeDays $days): Response
    {
        $request->validate(['branch' => ['nullable', 'string', 'size:26'], 'view' => ['nullable', 'in:week,2weeks,month'], 'date' => ['nullable', 'date_format:Y-m-d']]);
        $me = Employee::query()->where('user_id', $user->id)->first();
        $today = $me instanceof Employee ? $days->today($me) : now()->toDateString();
        $branches = $this->access->branches($user, $today);
        abort_if($branches === [], 403);

        $branch = collect($branches)->firstWhere('ulid', $request->string('branch')->toString())
            ?? collect($branches)->firstWhere('id', $me?->current_branch_id)
            ?? $branches[0];
        $view = $request->string('view', 'week')->toString();
        [$from, $to] = RosterBoard::range($view, $request->string('date', $today)->toString(), $branch);

        return Inertia::render('schedule/roster', [
            ...$board->build($user, $branch, $from, $to),
            'view' => $view,
            'today' => $today,
            'branches' => array_map(fn (Branch $b): array => ['id' => $b->ulid, 'name' => $b->getTranslations('name')], $branches),
        ]);
    }

    public function store(#[CurrentUser] User $user, Branch $branch, RosterShiftInputData $input, SaveRosterShift $save): RedirectResponse
    {
        $this->authorizeTarget($user, $branch, $input);
        $save->handle(Actor::user($user), $branch, $input);

        return back();
    }

    public function update(#[CurrentUser] User $user, RosterShift $shift, RosterShiftInputData $input, SaveRosterShift $save): RedirectResponse
    {
        abort_unless($this->access->canManageShift($user, $shift), 403);
        $branch = Branch::query()->findOrFail($shift->branch_id);
        $this->authorizeTarget($user, $branch, $input);
        $save->handle(Actor::user($user), $branch, $input, $shift);

        return back();
    }

    public function destroy(#[CurrentUser] User $user, RosterShift $shift, DeleteRosterShift $delete): RedirectResponse
    {
        abort_unless($this->access->canManageShift($user, $shift), 403);
        $delete->handle(Actor::user($user), $shift);

        return back();
    }

    public function publish(Request $request, #[CurrentUser] User $user, Branch $branch, PublishRoster $publish): RedirectResponse
    {
        $request->validate(['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from']]);
        abort_unless($this->access->canManageBranch($user, $branch, $request->string('from')->toString()), 403);
        $count = $publish->handle(Actor::user($user), $branch, $request->string('from')->toString(), $request->string('to')->toString());
        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{0} Nothing new to publish.|{1} One shift published.|[2,*] :count shifts published.', $count, ['count' => $count])]);

        return back();
    }

    public function copy(Request $request, #[CurrentUser] User $user, Branch $branch, CopyRosterWeek $copy): RedirectResponse
    {
        $request->validate(['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'different:from']]);
        abort_unless($this->access->canManageBranch($user, $branch, $request->string('to')->toString()), 403);
        $count = $copy->handle(Actor::user($user), $branch, $request->string('from')->toString(), $request->string('to')->toString());
        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{0} Nothing to copy.|{1} One shift copied as a draft.|[2,*] :count shifts copied as drafts.', $count, ['count' => $count])]);

        return back();
    }

    public function rotation(Request $request, #[CurrentUser] User $user, Branch $branch, ApplyRotation $apply): RedirectResponse
    {
        $request->validate([
            'rotation' => ['required', 'string', 'size:26', 'exists:rotation_patterns,ulid'],
            'people' => ['required', 'array', 'min:1', 'max:200'],
            'people.*' => ['string', 'size:26'],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $people = Employee::query()->whereIn('ulid', $request->array('people'))->get()->filter(fn (Employee $e): bool => $this->access->canManageEmployee($user, $e))->values()->all();
        $people = array_values($people);
        abort_if($people === [], 403);
        $pattern = RotationPattern::query()->where('ulid', $request->string('rotation')->toString())->firstOrFail();
        $count = $apply->handle(Actor::user($user), $branch, $pattern, $people, $request->string('from')->toString(), $request->string('to')->toString());
        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{0} Nothing added: those days already have shifts.|{1} One shift added as a draft.|[2,*] :count shifts added as drafts.', $count, ['count' => $count])]);

        return back();
    }

    public function coverage(Request $request, #[CurrentUser] User $user, Branch $branch, SaveCoverageTargets $save): RedirectResponse
    {
        $request->validate(['required' => ['required', 'array'], 'required.*' => ['integer', 'between:0,999']]);
        abort_unless($this->access->canManageBranch($user, $branch, now()->toDateString()), 403);
        $required = [];

        foreach ($request->array('required') as $weekday => $count) {
            if (is_numeric($weekday) && (int) $weekday >= 1 && (int) $weekday <= 7 && is_numeric($count)) {
                $required[(int) $weekday] = (int) $count;
            }
        }

        $save->handle($branch, $required);

        return back();
    }

    public function assign(#[CurrentUser] User $user, OpenShiftClaim $claim, AssignOpenShift $assign): RedirectResponse
    {
        $shift = RosterShift::query()->findOrFail($claim->roster_shift_id);
        abort_unless($this->access->canManageShift($user, $shift), 403);
        $assign->handle(Actor::user($user), $claim);

        return back();
    }

    private function authorizeTarget(User $user, Branch $branch, RosterShiftInputData $input): void
    {
        $employee = $input->employeeId === null ? null : Employee::query()->where('ulid', $input->employeeId)->first();

        abort_unless($employee instanceof Employee
            ? $this->access->canManageEmployee($user, $employee)
            : $this->access->canManageBranch($user, $branch, $input->date), 403);
    }
}
