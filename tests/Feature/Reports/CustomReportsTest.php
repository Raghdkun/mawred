<?php

declare(strict_types=1);

use App\Domain\Access\Models\User;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Reports\Enums\RunStatus;
use App\Domain\Reports\Models\ReportRun;
use App\Domain\Reports\Models\ReportSchedule;
use App\Domain\Reports\Models\SavedReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Phase 8b: the custom builder (D118, D121), saved reports shared in each viewer's own scope
| (D119), and schedules that run for their owner (D120).
*/
beforeEach(function (): void {
    Mail::fake();
    Storage::fake('local');
    seedAccess();
    $this->alpha = Branch::factory()->riyadh()->create(['name' => ['en' => 'Alpha branch', 'ar' => 'فرع ألفا']]);
    $this->bravo = Branch::factory()->riyadh()->create(['name' => ['en' => 'Bravo branch', 'ar' => 'فرع برافو']]);
    $this->hr = userWithRoles('hr_admin');
    $this->hr->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now(), 'timezone' => 'Asia/Riyadh'])->save();
    $this->managerUser = userWithRoles('branch_manager');
    $this->manager = Employee::factory()->placedIn($this->alpha)->create(['user_id' => $this->managerUser->id, 'name_en' => 'Alpha Manager']);

    foreach (['Alpha' => $this->alpha, 'Bravo' => $this->bravo] as $label => $branch) {
        Employee::factory()->count(2)->placedIn($branch)->sequence(['name_en' => "{$label} One", 'date_of_birth' => '1990-05-01'], ['name_en' => "{$label} Two", 'date_of_birth' => '1995-05-01'])->create();
    }
});

/** @return array<string, mixed> */
function customPeopleList(array $extra = []): array
{
    return ['dataset' => 'people', 'columns' => ['employee_name', 'branch', 'hire_date'], 'sort' => 'employee_name', 'direction' => 'asc', ...$extra];
}

function customSave(object $test, array $definition, string $name = 'Our people'): SavedReport
{
    $test->actingAs($test->hr)->post(route('reports.saved.store'), ['name' => $name, 'description' => null, 'definition' => $definition])->assertRedirect();

    return SavedReport::query()->latest('id')->firstOrFail();
}

function customRows(object $test, User $user, SavedReport $report): array
{
    $rows = [];
    $test->actingAs($user)->get(route('reports.show', $report->reportKey()))
        ->assertInertia(function (Assert $page) use (&$rows): Assert {
            return $page->component('reports/show')->loadDeferredProps(function (Assert $reload) use (&$rows): Assert {
                $rows = $reload->toArray()['props']['result']['rows'];

                return $reload;
            });
        });

    return $rows;
}

it('lets HR build, preview and save a report, and keeps others out of the builder', function (): void {
    $this->actingAs(userWithRoles('employee'))->get(route('reports.builder.create'))->assertForbidden();
    $this->actingAs($this->managerUser)->get(route('reports.builder.create'))->assertForbidden();

    $this->actingAs($this->hr)->get(route('reports.builder.create'))
        ->assertInertia(fn (Assert $page): Assert => $page->component('reports/builder')->has('datasets', 6)->where('saved', null));

    $preview = $this->actingAs($this->hr)->postJson(route('reports.builder.preview'), ['definition' => ['dataset' => 'people', 'groupBy' => 'branch', 'measure' => 'count', 'chart' => 'bar', 'sort' => 'group']])
        ->assertOk()->json();
    expect(collect($preview['rows'])->pluck('measure', 'group')->all())->toMatchArray(['Alpha branch' => 3, 'Bravo branch' => 2])
        ->and($preview['chart']['type'])->toBe('bar');

    $this->actingAs($this->hr)->postJson(route('reports.builder.preview'), ['definition' => ['dataset' => 'people', 'columns' => ['salary']]])
        ->assertUnprocessable();
    $this->actingAs($this->hr)->postJson(route('reports.builder.preview'), ['definition' => customPeopleList(['filters' => [['field' => 'hire_date', 'op' => 'contains', 'value' => 'x']]])])
        ->assertUnprocessable();

    $saved = customSave($this, customPeopleList(['filters' => [['field' => 'employee_name', 'op' => 'contains', 'value' => 'One']]]));
    expect($saved->definition['filters'][0])->toEqual(['field' => 'employee_name', 'op' => 'contains', 'value' => 'One'])
        ->and(collect(customRows($this, $this->hr, $saved))->pluck('employee_name')->all())->toBe(['Alpha One', 'Bravo One']);
});

it('runs a shared report in each viewer\'s own scope, never the owner\'s', function (): void {
    $saved = customSave($this, customPeopleList());

    // Not shared yet: the manager can't open it.
    $this->actingAs($this->managerUser)->get(route('reports.show', $saved->reportKey()))->assertNotFound();

    $this->actingAs($this->hr)->put(route('reports.saved.share', $saved), ['people' => [$this->manager->ulid], 'roles' => []])->assertRedirect();

    expect(collect(customRows($this, $this->hr, $saved))->pluck('branch')->unique()->sort()->values()->all())->toBe(['Alpha branch', 'Bravo branch'])
        ->and(collect(customRows($this, $this->managerUser, $saved))->pluck('branch')->unique()->values()->all())->toBe(['Alpha branch']);

    // Sharing with a role reaches everyone holding it; the library lists it for them.
    $this->actingAs($this->hr)->put(route('reports.saved.share', $saved), ['people' => [], 'roles' => ['branch_manager']])->assertRedirect();
    $other = userWithRoles('branch_manager');
    Employee::factory()->placedIn($this->bravo)->create(['user_id' => $other->id]);
    $this->actingAs($other)->get(route('reports.index'))->assertInertia(fn (Assert $page): Assert => $page->where('saved.0.key', $saved->reportKey())->where('saved.0.mine', false));
    expect(collect(customRows($this, $other, $saved))->pluck('branch')->unique()->values()->all())->toBe(['Bravo branch']);

    // Only the owner changes it.
    $this->actingAs($other)->put(route('reports.saved.update', $saved), ['name' => 'Mine now', 'definition' => customPeopleList()])->assertForbidden();
    $this->actingAs($other)->put(route('reports.saved.share', $saved), ['people' => [], 'roles' => []])->assertForbidden();
    $this->actingAs($other)->delete(route('reports.saved.destroy', $saved))->assertForbidden();
});

it('hides sensitive fields from viewers without the field permission', function (): void {
    $withBirthday = customSave($this, customPeopleList(['columns' => ['employee_name', 'date_of_birth']]), 'Birthdays');
    $byBirthday = customSave($this, customPeopleList(['filters' => [['field' => 'date_of_birth', 'op' => 'between', 'value' => ['from' => '1990-01-01', 'to' => '1990-12-31']]]]), 'Born in 1990');

    foreach ([$withBirthday, $byBirthday] as $report) {
        $this->actingAs($this->hr)->put(route('reports.saved.share', $report), ['people' => [$this->manager->ulid], 'roles' => []]);
    }

    expect(array_keys(customRows($this, $this->hr, $withBirthday)[0]))->toContain('date_of_birth')
        ->and(array_keys(customRows($this, $this->managerUser, $withBirthday)[0]))->not->toContain('date_of_birth');

    // A report that filters on a hidden field would reveal it, so it doesn't open at all.
    $this->actingAs($this->managerUser)->get(route('reports.show', $byBirthday->reportKey()))->assertNotFound();
    $this->actingAs($this->managerUser)->postJson(route('reports.builder.preview'), ['definition' => customPeopleList(['columns' => ['date_of_birth']])])->assertForbidden();
});

it('exports a saved report like any other', function (): void {
    $saved = customSave($this, customPeopleList(), 'Team list');

    $this->actingAs($this->hr)->post(route('reports.exports.store', $saved->reportKey()), ['format' => 'csv'])->assertRedirect();

    $run = ReportRun::query()->sole();
    expect($run->status)->toBe(RunStatus::Ready)->and($run->rows)->toBe(5)->and($run->file_name)->toStartWith('Team-list-');
    expect(Storage::disk('local')->get((string) $run->file_path))->toContain('Alpha One');
});

it('schedules a report for its owner and runs it when due', function (): void {
    $this->actingAs($this->managerUser)->post(route('reports.schedules.store'), [
        'report' => 'people.headcount', 'format' => 'xlsx', 'frequency' => 'weekly', 'weekday' => 7, 'monthDay' => null, 'hour' => 7, 'params' => ['branch' => $this->alpha->ulid],
    ])->assertRedirect(route('reports.schedules.index'));

    $schedule = ReportSchedule::query()->sole();
    expect($schedule->next_run_at->setTimezone($schedule->timezone)->dayOfWeekIso)->toBe(7)
        ->and($schedule->next_run_at->setTimezone($schedule->timezone)->hour)->toBe(7)
        ->and($schedule->params)->toBe(['branch' => $this->alpha->ulid]);

    $this->travelTo($schedule->next_run_at->addMinutes(5));
    $this->artisan('reports:schedules')->assertSuccessful();

    $run = ReportRun::query()->sole();
    expect($run->user_id)->toBe($this->managerUser->id)->and($run->status)->toBe(RunStatus::Ready)
        ->and($schedule->fresh()?->last_run_id)->toBe($run->id)
        ->and($schedule->fresh()?->next_run_at->isAfter(now()->addDays(6)))->toBeTrue();

    // Running again before the next one is due does nothing.
    $this->artisan('reports:schedules')->assertSuccessful();
    expect(ReportRun::query()->count())->toBe(1);

    $this->actingAs($this->managerUser)->get(route('reports.schedules.index'))
        ->assertInertia(fn (Assert $page): Assert => $page->component('reports/schedules')->where('schedules.0.lastRunStatus', 'ready'));
    $this->actingAs($this->hr)->delete(route('reports.schedules.destroy', $schedule))->assertNotFound();
});

it('schedules a report that has no filters to keep', function (): void {
    $this->actingAs($this->hr)->post(route('reports.schedules.store'), [
        'report' => 'training.completion', 'format' => 'pdf', 'frequency' => 'daily', 'hour' => 6, 'params' => [],
    ])->assertRedirect(route('reports.schedules.index'));

    expect(ReportSchedule::query()->sole()->params)->toBe([]);
});

it('pauses a schedule whose owner lost access, and says why', function (): void {
    $this->actingAs($this->managerUser)->post(route('reports.schedules.store'), [
        'report' => 'people.headcount', 'format' => 'csv', 'frequency' => 'daily', 'weekday' => null, 'monthDay' => null, 'hour' => 6, 'params' => ['range' => 'last_month'],
    ])->assertRedirect();
    $this->managerUser->syncRoles(['employee']);
    app()->forgetScopedInstances();

    $this->travelTo(ReportSchedule::query()->sole()->next_run_at->addMinute());
    $this->artisan('reports:schedules')->assertSuccessful();

    $schedule = ReportSchedule::query()->sole();
    expect($schedule->paused_at)->not->toBeNull()
        ->and($schedule->paused_reason)->toContain('no longer have access')
        ->and(ReportRun::query()->count())->toBe(0)
        ->and($this->managerUser->notifications()->sole()->data['event'] ?? null)->toBe('reports.schedule_paused');
});

it('runs a scheduled period relative to each run', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-20 08:00', 'UTC'));
    $this->actingAs($this->hr)->post(route('reports.schedules.store'), [
        'report' => 'people.movements', 'format' => 'csv', 'frequency' => 'monthly', 'weekday' => null, 'monthDay' => 1, 'hour' => 7, 'params' => ['range' => 'last_month', 'from' => '2020-01-01'],
    ])->assertRedirect();

    expect(ReportSchedule::query()->sole()->params)->toBe(['range' => 'last_month']);

    $this->travelTo(CarbonImmutable::parse('2026-11-01 05:00', 'UTC'));
    $this->artisan('reports:schedules')->assertSuccessful();

    expect(ReportRun::query()->sole()->params)->toMatchArray(['from' => '2026-10-01', 'to' => '2026-10-31']);
});

it('deletes a saved report with its schedules', function (): void {
    $saved = customSave($this, customPeopleList());
    $this->actingAs($this->hr)->post(route('reports.schedules.store'), [
        'report' => $saved->reportKey(), 'format' => 'csv', 'frequency' => 'daily', 'weekday' => null, 'monthDay' => null, 'hour' => 6, 'params' => [],
    ])->assertRedirect();

    $this->actingAs($this->hr)->delete(route('reports.saved.destroy', $saved))->assertRedirect(route('reports.index'));

    expect(SavedReport::query()->count())->toBe(0)->and(ReportSchedule::query()->count())->toBe(0);
});
