<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Reports;

use App\Domain\Access\Models\User;
use App\Domain\Reports\Actions\DeleteReportSchedule;
use App\Domain\Reports\Actions\PauseReportSchedule;
use App\Domain\Reports\Actions\SaveReportSchedule;
use App\Domain\Reports\Data\ReportScheduleData;
use App\Domain\Reports\Data\ReportScheduleInputData;
use App\Domain\Reports\Models\ReportSchedule;
use App\Domain\Reports\Support\ReportRegistry;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** My scheduled reports (D120): set up from a report, listed here, paused, changed or deleted. */
final class SchedulesController extends Controller
{
    public function index(ReportRegistry $registry, #[CurrentUser] User $user): Response
    {
        Gate::authorize('reports.view');
        $locale = app()->getLocale();
        $schedules = ReportSchedule::query()->with('lastRun')->where('user_id', $user->id)->oldest()->get();

        return Inertia::render('reports/schedules', [
            'schedules' => array_values($schedules->map(fn (ReportSchedule $s): ReportScheduleData => ReportScheduleData::fromSchedule($s, $registry->resolve($s->report, $user), $locale))->all()),
        ]);
    }

    public function store(ReportScheduleInputData $input, SaveReportSchedule $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('reports.export');
        $save->handle(Actor::user($user), $input);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Report scheduled. Each file will be in My exports.')]);

        return to_route('reports.schedules.index');
    }

    public function update(ReportScheduleInputData $input, ReportSchedule $schedule, SaveReportSchedule $save, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless($schedule->user_id === $user->id, 404);
        $save->handle(Actor::user($user), $input, $schedule);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Schedule saved.')]);

        return back();
    }

    public function pause(Request $request, ReportSchedule $schedule, PauseReportSchedule $pause, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless($schedule->user_id === $user->id, 404);
        $request->validate(['paused' => ['required', 'boolean']]);
        $pause->handle(Actor::user($user), $schedule, $request->boolean('paused'));

        return back();
    }

    public function destroy(ReportSchedule $schedule, DeleteReportSchedule $delete, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless($schedule->user_id === $user->id, 404);
        $delete->handle(Actor::user($user), $schedule);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Schedule deleted.')]);

        return back();
    }
}
