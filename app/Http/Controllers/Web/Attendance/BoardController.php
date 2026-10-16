<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Attendance;

use App\Domain\Access\Enums\Scope;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Attendance\Actions\CorrectAttendance;
use App\Domain\Attendance\Actions\EnsureAttendanceDays;
use App\Domain\Attendance\Data\AttendanceDayData;
use App\Domain\Attendance\Data\PunchData;
use App\Domain\Attendance\Models\AttendanceDay;
use App\Domain\Attendance\Models\AttendanceEvent;
use App\Domain\Attendance\Queries\AttendanceBoardQuery;
use App\Domain\Attendance\Support\MonthlySheet;
use App\Domain\Leave\Support\EmployeeDays;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Scheduling\Queries\RosterPeopleQuery;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Daily attendance board (handoff 26): who is present, late, absent or on leave at a branch on a
 * date, the day's punches, corrections with a reason (audited) and the monthly sheet.
 */
final class BoardController extends Controller
{
    public function show(Request $request, #[CurrentUser] User $user, EmployeeDays $days, AttendanceBoardQuery $board, RosterPeopleQuery $people, EnsureAttendanceDays $ensure, ScopeResolver $scopes): Response
    {
        // The board is for people who look after others; your own days are on Check in.
        $widest = $scopes->for($user)->widestScope('attendance.view');
        abort_if(! $widest instanceof Scope || $widest === Scope::Own, 403);
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'branch' => ['nullable', 'string', 'size:26'], 'day' => ['nullable', 'string', 'size:26']]);
        $me = Employee::query()->where('user_id', $user->id)->first();
        $today = $me instanceof Employee ? $days->today($me) : now()->toDateString();
        $date = min($request->string('date', $today)->toString(), $today);
        $branches = Branch::query()->where('is_active', true)->orderBy('code')->get();
        $branch = $branches->firstWhere('ulid', $request->string('branch')->toString()) ?? $branches->firstWhere('id', $me?->current_branch_id) ?? $branches->first();
        abort_unless($branch instanceof Branch, 404);

        $ensure->handle($people->through('attendance.view')->in($branch->id, $date)->visibleTo($user)->limit(1000)->get(), $date);
        $rows = $board->between($date, $date)->inBranch($branch->id)->visibleTo($user)->get();
        $flagged = AttendanceEvent::query()->whereIn('employee_id', $rows->pluck('employee_id'))->whereDate('local_date', $date)->where('flagged', true)->whereNull('voided_at')->pluck('employee_id')->flip();
        $canCorrect = Gate::allows('attendance.update')
            ? resolve(RosterPeopleQuery::class)->through('attendance.update')->in($branch->id, $date)->visibleTo($user)->where('employees.user_id', '!=', $user->id)->pluck('employees.ulid')->all()
            : [];
        $detail = $request->filled('day') ? $rows->firstWhere('ulid', $request->string('day')->toString()) : null;

        return Inertia::render('attendance/board', [
            'date' => $date,
            'today' => $today,
            'branch' => ['id' => $branch->ulid, 'name' => $branch->getTranslations('name'), 'timezone' => $branch->timezone],
            'branches' => array_values($branches->map(fn (Branch $b): array => ['id' => $b->ulid, 'name' => $b->getTranslations('name')])->all()),
            'days' => array_values($rows->map(fn (AttendanceDay $d): AttendanceDayData => AttendanceDayData::fromModel($d, $flagged->has($d->employee_id)))->all()),
            'canCorrect' => $canCorrect,
            'detail' => $detail instanceof AttendanceDay ? [
                'day' => $detail->ulid,
                'punches' => array_values(AttendanceEvent::query()->where('employee_id', $detail->employee_id)->whereDate('local_date', $date)->oldest('occurred_at')->get()->map(PunchData::fromModel(...))->all()),
            ] : null,
            'can' => ['export' => Gate::allows('attendance.export'), 'configure' => Gate::allows('attendance.configure')],
        ]);
    }

    public function correct(Request $request, #[CurrentUser] User $user, Employee $employee, CorrectAttendance $correct): RedirectResponse
    {
        Gate::authorize('correctAttendance', $employee);
        $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'checkIn' => ['nullable', 'date_format:H:i', 'required_without:checkOut'],
            'checkOut' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:300'],
        ]);
        $correct->handle(Actor::user($user), $employee, $request->string('date')->toString(), $request->filled('checkIn') ? $request->string('checkIn')->toString() : null, $request->filled('checkOut') ? $request->string('checkOut')->toString() : null, $request->string('reason')->toString());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Attendance corrected.')]);

        return back();
    }

    public function export(Request $request, #[CurrentUser] User $user, AttendanceBoardQuery $board): BinaryFileResponse
    {
        Gate::authorize('attendance.export');
        $request->validate(['month' => ['required', 'date_format:Y-m'], 'branch' => ['required', 'string', 'size:26']]);
        $branch = Branch::query()->where('ulid', $request->string('branch')->toString())->firstOrFail();
        $month = CarbonImmutable::parse($request->string('month')->toString().'-01');
        $days = $board->through('attendance.export')->between($month->toDateString(), $month->endOfMonth()->toDateString())->inBranch($branch->id)->visibleTo($user);
        $path = storage_path('app/tmp/attendance-'.$branch->code.'-'.$month->format('Y-m').'-'.bin2hex(random_bytes(4)).'.xlsx');
        File::ensureDirectoryExists(dirname($path));
        MonthlySheet::write($path, $days, $branch->timezone);

        activity('attendance')->causedBy($user)->performedOn($branch)->event('exported')->withProperties(['month' => $month->format('Y-m')])->log('Attendance sheet exported');

        return response()->download($path, 'attendance-'.$branch->code.'-'.$month->format('Y-m').'.xlsx')->deleteFileAfterSend();
    }
}
