<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Organization\Models\Branch;
use App\Domain\Policies\Actions\DeleteHolidayCalendar;
use App\Domain\Policies\Actions\DeleteHolidayRule;
use App\Domain\Policies\Actions\ImportHolidayDates;
use App\Domain\Policies\Actions\InstallHolidayPreset;
use App\Domain\Policies\Actions\SaveHolidayCalendar;
use App\Domain\Policies\Actions\SaveHolidayRule;
use App\Domain\Policies\Data\HolidayCalendarData;
use App\Domain\Policies\Data\HolidayCalendarInputData;
use App\Domain\Policies\Data\HolidayDayData;
use App\Domain\Policies\Data\HolidayRuleInputData;
use App\Domain\Policies\Models\HolidayCalendar;
use App\Domain\Policies\Models\HolidayRule;
use App\Domain\Policies\Support\BranchHolidays;
use App\Domain\Policies\Support\HolidayPresets;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Holiday calendar manager (screen 21): a branch's year of holidays, and the shared calendars and
 * rules behind it (D47).
 */
final class HolidayCalendarsController extends Controller
{
    public function index(Request $request, BranchHolidays $holidays): Response
    {
        Gate::authorize('policies.view');
        $request->validate([
            'branch' => ['nullable', 'string', 'exists:branches,ulid'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
        ]);

        $year = $request->integer('year', CarbonImmutable::now('UTC')->year);
        $branch = $request->filled('branch')
            ? Branch::query()->where('ulid', $request->string('branch')->toString())->first()
            : Branch::query()->oldest('id')->first();

        $calendars = HolidayCalendar::query()->with(['branches', 'rules' => fn ($q) => $q->orderByRaw('coalesce(month, extract(month from date)::int), coalesce(day, extract(day from date)::int), id')])
            ->orderByRaw("name->>'en'")
            ->get();
        $installed = $calendars->pluck('preset_key')->filter()->all();

        return Inertia::render('policies/holidays', [
            'year' => $year,
            'branch' => $branch instanceof Branch ? [
                'id' => $branch->ulid,
                'label' => $branch->getTranslation('name', app()->getLocale()),
                'description' => $branch->code,
                'weekend' => $branch->weekend_days,
            ] : null,
            'days' => $branch instanceof Branch
                ? array_map(HolidayDayData::fromOccurrence(...), $holidays->between($branch, "{$year}-01-01", "{$year}-12-31"))
                : [],
            'calendars' => $calendars->map(HolidayCalendarData::fromCalendar(...)),
            'presets' => array_map(fn (string $key, array $preset): array => [
                'key' => $key,
                'name' => $preset['name'],
                'installed' => in_array($key, $installed, true),
            ], array_keys(HolidayPresets::all()), HolidayPresets::all()),
            'can' => ['configure' => Gate::allows('policies.configure')],
        ]);
    }

    public function store(HolidayCalendarInputData $data, SaveHolidayCalendar $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('policies.configure');

        $save->handle(Actor::user($user), $data);

        return $this->saved(__('Holiday calendar saved.'));
    }

    public function update(HolidayCalendarInputData $data, HolidayCalendar $calendar, SaveHolidayCalendar $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('policies.configure');

        $save->handle(Actor::user($user), $data, $calendar);

        return $this->saved(__('Holiday calendar saved.'));
    }

    public function destroy(HolidayCalendar $calendar, DeleteHolidayCalendar $delete): RedirectResponse
    {
        Gate::authorize('policies.configure');

        $delete->handle($calendar);

        return $this->saved(__('Holiday calendar archived.'));
    }

    public function storeRule(HolidayRuleInputData $data, HolidayCalendar $calendar, SaveHolidayRule $save): RedirectResponse
    {
        Gate::authorize('policies.configure');

        $save->handle($calendar, $data);

        return $this->saved(__('Holiday saved.'));
    }

    public function updateRule(HolidayRuleInputData $data, HolidayCalendar $calendar, HolidayRule $rule, SaveHolidayRule $save): RedirectResponse
    {
        Gate::authorize('policies.configure');
        abort_unless($rule->holiday_calendar_id === $calendar->id, 404);

        $save->handle($calendar, $data, $rule);

        return $this->saved(__('Holiday saved.'));
    }

    public function destroyRule(HolidayCalendar $calendar, HolidayRule $rule, DeleteHolidayRule $delete): RedirectResponse
    {
        Gate::authorize('policies.configure');
        abort_unless($rule->holiday_calendar_id === $calendar->id, 404);

        $delete->handle($rule);

        return $this->saved(__('Holiday removed.'));
    }

    public function import(Request $request, HolidayCalendar $calendar, ImportHolidayDates $import): RedirectResponse
    {
        Gate::authorize('policies.configure');
        $request->validate(['file' => ['required', 'file', 'max:1024', 'mimes:csv,txt,xlsx']]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $result = $import->handle($calendar, $file->path(), $file->getClientOriginalExtension() === 'xlsx' ? 'xlsx' : 'csv');

        return $this->saved(__('Imported :imported holidays, skipped :skipped already there.', $result));
    }

    public function installPreset(Request $request, InstallHolidayPreset $install, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('policies.configure');
        $request->validate([
            'preset' => ['required', Rule::in(array_keys(HolidayPresets::all()))],
            'branchIds' => ['array'],
            'branchIds.*' => ['string', 'exists:branches,ulid'],
        ]);

        /** @var list<string> $branchIds */
        $branchIds = $request->input('branchIds', []);
        $install->handle(Actor::user($user), $request->string('preset')->toString(), $branchIds);

        return $this->saved(__('Holiday calendar saved.'));
    }

    private function saved(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
