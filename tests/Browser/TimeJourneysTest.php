<?php

declare(strict_types=1);

use App\Domain\Access\Models\User;
use App\Domain\Attendance\Actions\SaveKiosk;
use App\Domain\Attendance\Models\AttendanceEvent;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Actions\SeedPolicyDefaults;
use App\Domain\Requests\Actions\SeedRequestTypes;
use App\Domain\Scheduling\Actions\PublishRoster;
use App\Domain\Scheduling\Actions\SaveRosterShift;
use App\Domain\Scheduling\Actions\SaveShiftTemplate;
use App\Domain\Scheduling\Data\RosterShiftInputData;
use App\Domain\Scheduling\Data\ShiftTemplateInputData;
use App\Domain\Scheduling\Enums\ShiftStatus;
use App\Domain\Scheduling\Models\RosterShift;
use App\Support\Actors\Actor;
use Carbon\CarbonImmutable;

/*
| Phase 5b journeys (EN + AR, axe): plan and publish a shift, see it in My schedule, check in,
| the daily board, shifts settings, kiosks and the kiosk screen.
*/
beforeEach(function (): void {
    seedAccess();
    resolve(SeedPolicyDefaults::class)->handle();
    resolve(SeedRequestTypes::class)->handle();
    $this->branch = Branch::factory()->riyadh()->create(['name' => ['en' => 'Riyadh HQ', 'ar' => 'المقر الرئيسي'], 'geofence_lat' => '24.713600', 'geofence_lng' => '46.675300', 'geofence_radius_m' => 150]);
    $person = function (string $role, string $name) : User {
        $user = userWithRoles($role);
        $user->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
        Employee::factory()->placedIn($this->branch)->create(['user_id' => $user->id, 'name_en' => $name, 'hire_date' => '2024-01-01']);

        return $user;
    };

    $this->manager = $person('branch_manager', 'Saud Manager');
    $this->hr = $person('hr_officer', 'Huda HR');
    $this->admin = $person('hr_admin', 'Amal Admin');
    $this->worker = $person('employee', 'Rami Worker');
    $this->workerEmployee = Employee::query()->where('user_id', $this->worker->id)->sole();
    $this->morning = resolve(SaveShiftTemplate::class)->handle(ShiftTemplateInputData::from(['code' => 'AM', 'name' => ['en' => 'Morning', 'ar' => 'صباحية'], 'startsAt' => '07:00', 'endsAt' => '15:00', 'breakMinutes' => 30, 'color' => 2]));
    $this->today = CarbonImmutable::now('Asia/Riyadh')->toDateString();
});

it('plans and publishes a shift that then shows in My schedule', function (string $locale, array $text): void {
    foreach ([$this->manager, $this->worker] as $user) {
        $user->forceFill(['locale' => $locale])->save();
    }

    $this->actingAs($this->manager);
    visit('/roster')
        ->on()->macbook14()
        ->wait(1)
        ->click("[data-cell=\"{$this->workerEmployee->ulid}|{$this->today}\"] button")
        ->wait(0.6)
        ->click('[data-save-shift]')
        ->wait(1)
        ->assertPresent('[data-shift][data-status="draft"]')
        ->assertSee($text['unpublished'])
        ->click('[data-publish]')
        ->wait(1)
        ->assertPresent('[data-shift][data-status="published"]')
        ->assertNoJavaScriptErrors();

    expect(RosterShift::query()->sole()->status)->toBe(ShiftStatus::Published);

    $this->actingAs($this->worker);
    visit('/schedule')->wait(1)->assertPresent('[data-my-shift]')->assertSee('07:00')->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
})->with([
    'en' => ['en', ['unpublished' => '1 change not published']],
    'ar' => ['ar', ['unpublished' => 'تغيير واحد غير منشور']],
]);

it('checks in from the phone', function (string $locale, array $text): void {
    $this->worker->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->worker);

    visit('/attendance')
        ->on()->mobile()
        ->wait(1)
        ->assertNoAccessibilityIssues()
        ->click('[data-punch="in"]')
        ->wait(1.5)
        ->assertPresent('[data-punch="out"]')
        ->assertPresent('[data-recent-punch="in"]')
        ->assertSee($text['out'])
        ->assertNoJavaScriptErrors();

    expect(AttendanceEvent::query()->where('employee_id', $this->workerEmployee->id)->count())->toBe(1);
})->with([
    'en' => ['en', ['out' => 'Check out']],
    'ar' => ['ar', ['out' => 'تسجيل الانصراف']],
]);

it('renders the time screens accessibly in both languages', function (string $locale): void {
    foreach ([$this->manager, $this->hr, $this->admin, $this->worker] as $user) {
        $user->forceFill(['locale' => $locale])->save();
    }

    resolve(SaveRosterShift::class)->handle(Actor::system('test'), $this->branch, RosterShiftInputData::from(['date' => $this->today, 'templateId' => $this->morning->ulid]));
    resolve(PublishRoster::class)->handle(Actor::system('test'), $this->branch, $this->today, $this->today);
    ['token' => $token] = resolve(SaveKiosk::class)->handle($this->branch, 'Front desk');

    $this->actingAs($this->manager);
    visit('/roster')->on()->macbook14()->wait(1)->assertPresent('[data-roster-grid]')->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();

    $this->actingAs($this->hr);
    visit('/attendance/board')->on()->macbook14()->wait(1)->assertPresent('[data-board-columns]')->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();

    $this->actingAs($this->admin);
    foreach (['/schedule/settings', '/attendance/kiosks'] as $path) {
        visit($path)->wait(1)->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    }

    visit("/kiosk/{$token}")->wait(1.5)->assertPresent('[data-kiosk-code]')->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
})->with(['en', 'ar']);

it('keeps the phone layouts within the screen', function (): void {
    $this->actingAs($this->worker);

    foreach (['/schedule', '/attendance'] as $path) {
        visit($path)->on()->mobile()->wait(1)
            ->assertScript('document.documentElement.scrollWidth <= document.documentElement.clientWidth', true)
            ->assertNoJavaScriptErrors();
    }

    $this->actingAs($this->manager);
    visit('/roster')->on()->mobile()->wait(1)
        ->assertScript("document.querySelector('[data-roster-grid]')?.offsetParent ?? null", null)
        ->assertNoJavaScriptErrors();
});

/*
 * Visual check (handoff, D32): 390 / 834 / 1440 × LTR / RTL; dark for the ★ screens (roster
 * planner and check-in).
 */
it('captures the time screens for review', function (string $locale): void {
    foreach ([$this->manager, $this->hr, $this->worker] as $user) {
        $user->forceFill(['locale' => $locale])->save();
    }

    foreach ([0, 1, 2] as $offset) {
        resolve(SaveRosterShift::class)->handle(Actor::system('test'), $this->branch, RosterShiftInputData::from([
            'date' => CarbonImmutable::parse($this->today)->addDays($offset)->toDateString(), 'employeeId' => $this->workerEmployee->ulid, 'templateId' => $this->morning->ulid,
        ]));
    }
    resolve(PublishRoster::class)->handle(Actor::system('test'), $this->branch, $this->today, CarbonImmutable::parse($this->today)->addDay()->toDateString());

    $shots = [
        [$this->manager, 'roster', '/roster'],
        [$this->worker, 'mine', '/schedule'],
        [$this->worker, 'clock', '/attendance'],
        [$this->hr, 'board', '/attendance/board'],
    ];

    foreach ($shots as [$user, $name, $path]) {
        $this->actingAs($user);
        visit($path)->on()->mobile()->wait(1)->screenshot(true, "time-{$name}-{$locale}-390");
        visit($path)->on()->iPadPro()->wait(1)->screenshot(true, "time-{$name}-{$locale}-834");
        visit($path)->on()->macbook14()->wait(1)->screenshot(true, "time-{$name}-{$locale}-1440");
    }

    $this->actingAs($this->manager);
    visit('/roster')->on()->macbook14()->inDarkMode()->wait(1)->screenshot(true, "time-roster-{$locale}-dark");
    $this->actingAs($this->worker);
    visit('/attendance')->on()->mobile()->inDarkMode()->wait(1)->screenshot(true, "time-clock-{$locale}-dark");
})->with(['en', 'ar'])->skip(fn (): bool => ! env('SCREENSHOTS'), 'Set SCREENSHOTS=1 to capture.');
