<?php

declare(strict_types=1);

use App\Domain\Access\Models\User;
use App\Domain\Calendar\Actions\SaveBookingPage;
use App\Domain\Calendar\Actions\SaveEvent;
use App\Domain\Calendar\Data\BookingPageInputData;
use App\Domain\Calendar\Data\EventInputData;
use App\Domain\Calendar\Models\CalendarEvent;
use App\Domain\Calendar\Models\EventParticipant;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Actions\SeedPolicyDefaults;
use App\Support\Actors\Actor;
use Carbon\CarbonImmutable;

/*
| Phase 5b-2 journeys (EN + AR, axe): find a time and book it, reply to an invitation, book a slot
| from a booking page, and the calendar screens.
*/
beforeEach(function (): void {
    seedAccess();
    resolve(SeedPolicyDefaults::class)->handle();
    $branch = Branch::factory()->riyadh()->create(['name' => ['en' => 'Riyadh HQ', 'ar' => 'المقر الرئيسي']]);
    $person = function (string $role, string $name) use ($branch): User {
        $user = userWithRoles($role);
        $user->forceFill(['name' => $name, 'timezone' => 'Asia/Riyadh', 'two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
        Employee::factory()->placedIn($branch)->create(['user_id' => $user->id, 'name_en' => $name, 'hire_date' => '2024-01-01']);

        return $user;
    };

    $this->manager = $person('branch_manager', 'Saud Manager');
    $this->worker = $person('employee', 'Rami Worker');
    // The next Sunday (a Riyadh working day) at least a day away.
    $this->day = CarbonImmutable::now('Asia/Riyadh')->addDay()->next(CarbonImmutable::SUNDAY)->toDateString();
});

function invite(object $test): CalendarEvent
{
    return resolve(SaveEvent::class)->handle(Actor::user($test->manager), EventInputData::from([
        'title' => 'Weekly sync', 'startsAt' => $test->day.'T10:00:00+03:00', 'endsAt' => $test->day.'T10:30:00+03:00', 'timezone' => 'Asia/Riyadh',
        'required' => [$test->worker->ulid], 'recurrence' => ['freq' => 'weekly', 'count' => 4],
    ]));
}

it('finds a time with a colleague and sends the invitation', function (string $locale): void {
    $this->manager->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->manager);

    visit('/calendar/find')
        ->on()->macbook14()
        ->wait(1)
        ->click('button[role="combobox"][aria-haspopup="dialog"]')
        ->wait(0.5)
        ->type('[cmdk-input]', 'Rami')
        ->wait(1)
        ->click('[cmdk-item]')
        ->wait(1.5)
        ->assertPresent('[data-availability-grid]')
        ->assertPresent('[data-slot]')
        ->click('[data-suggestions] li:first-child button')
        ->wait(0.6)
        ->type('#meeting-title', 'Catch-up')
        ->click('[data-save-meeting]')
        ->wait(1.5)
        ->assertNoJavaScriptErrors();

    $event = CalendarEvent::query()->sole();
    expect($event->title)->toBe('Catch-up')
        ->and($event->participants()->pluck('user_id')->sort()->values()->all())->toBe(collect([$this->manager->id, $this->worker->id])->sort()->values()->all());
})->with(['en', 'ar']);

it('replies to an invitation from the calendar', function (string $locale, string $going): void {
    invite($this);
    $this->worker->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->worker);

    visit("/calendar?date={$this->day}")
        ->on()->macbook14()
        ->wait(1)
        ->click('[data-event]')
        ->wait(0.6)
        ->click('[data-reply="accepted"]')
        ->wait(1)
        ->assertSee($going)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    expect(EventParticipant::query()->where('user_id', $this->worker->id)->sole()->response->value)->toBe('accepted');
})->with([
    'en' => ['en', 'Going'],
    'ar' => ['ar', 'سأحضر'],
]);

it('books a slot from a booking page', function (string $locale): void {
    resolve(SaveBookingPage::class)->handle(Actor::user($this->manager), BookingPageInputData::from([
        'title' => ['en' => 'Office hours', 'ar' => 'الساعات المكتبية'], 'durationMinutes' => 30, 'timezone' => 'Asia/Riyadh',
        'windows' => [['weekday' => 7, 'from' => '10:00', 'to' => '12:00']], 'startsOn' => $this->day, 'audience' => 'everyone',
    ]));
    $this->worker->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->worker);

    visit('/book')
        ->wait(1)
        ->assertNoAccessibilityIssues()
        ->click('[data-booking-page] a')
        ->wait(1)
        ->click("[data-book-slot=\"{$this->day}T07:00:00+00:00\"]")
        ->wait(0.6)
        ->click('[data-confirm-booking]')
        ->wait(1.5)
        ->assertNoJavaScriptErrors();

    expect(CalendarEvent::query()->where('kind', 'booking')->count())->toBe(1);
})->with(['en', 'ar']);

it('renders the calendar screens accessibly in both languages', function (string $locale): void {
    invite($this);
    foreach ([$this->manager, $this->worker] as $user) {
        $user->forceFill(['locale' => $locale])->save();
    }

    $this->actingAs($this->manager);
    foreach (["/calendar?date={$this->day}", "/calendar?view=month&date={$this->day}", '/calendar/find', '/calendar/booking-pages'] as $path) {
        visit($path)->on()->macbook14()->wait(1)->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    }

    $this->actingAs($this->worker);
    visit("/calendar?date={$this->day}")->on()->mobile()->wait(1)
        ->assertPresent('[data-agenda]')
        ->assertScript('document.documentElement.scrollWidth <= document.documentElement.clientWidth', true)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->with(['en', 'ar']);

/*
 * Visual check (handoff 27, D32): 390 / 834 / 1440 × LTR / RTL.
 */
it('captures the calendar screens for review', function (string $locale): void {
    invite($this);
    foreach ([$this->manager, $this->worker] as $user) {
        $user->forceFill(['locale' => $locale])->save();
    }

    $shots = [
        [$this->manager, 'week', "/calendar?date={$this->day}"],
        [$this->manager, 'month', "/calendar?view=month&date={$this->day}"],
        [$this->manager, 'find', '/calendar/find'],
        [$this->worker, 'book', '/book'],
    ];

    foreach ($shots as [$user, $name, $path]) {
        $this->actingAs($user);
        visit($path)->on()->mobile()->wait(1)->screenshot(true, "calendar-{$name}-{$locale}-390");
        visit($path)->on()->iPadPro()->wait(1)->screenshot(true, "calendar-{$name}-{$locale}-834");
        visit($path)->on()->macbook14()->wait(1)->screenshot(true, "calendar-{$name}-{$locale}-1440");
    }
})->with(['en', 'ar'])->skip(fn (): bool => ! env('SCREENSHOTS'), 'Set SCREENSHOTS=1 to capture.');
