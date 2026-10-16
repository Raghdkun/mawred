<?php

declare(strict_types=1);

use App\Domain\Access\Models\User;
use App\Domain\Calendar\Actions\BookSlot;
use App\Domain\Calendar\Actions\CancelEvent;
use App\Domain\Calendar\Actions\SaveBookingPage;
use App\Domain\Calendar\Actions\SaveEvent;
use App\Domain\Calendar\Data\BookingPageInputData;
use App\Domain\Calendar\Data\EventInputData;
use App\Domain\Calendar\Enums\Response;
use App\Domain\Calendar\Models\CalendarEvent;
use App\Domain\Calendar\Models\EventParticipant;
use App\Domain\Calendar\Support\Occurrences;
use App\Domain\Calendar\Support\ReplyTokens;
use App\Domain\Integrations\Actions\HandleWhatsAppReply;
use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Models\WhatsAppContact;
use App\Domain\Notifications\Mail\NotificationMail;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Actions\SeedPolicyDefaults;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;

/*
| Phase 5b-2: meetings with replies (D75), free/busy privacy (D76), booking (D77) and repeats
| (D78). Riyadh (UTC+3, Fri/Sat weekend), policy hours 09:00–17:00. Sunday 1 March 2026.
*/
beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-03-01 04:00:00');
    Mail::fake();
    seedAccess();
    resolve(SeedPolicyDefaults::class)->handle();

    $this->a = Branch::factory()->riyadh()->create();
    $this->b = Branch::factory()->riyadh()->create();
    $person = function (Branch $branch, string $role, string $name): User {
        $user = userWithRoles($role);
        $user->forceFill(['name' => $name, 'two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
        Employee::factory()->placedIn($branch)->create(['user_id' => $user->id, 'name_en' => $name, 'hire_date' => '2024-01-01']);

        return $user;
    };

    $this->saud = $person($this->a, 'branch_manager', 'Saud');
    $this->rami = $person($this->a, 'employee', 'Rami');
    $this->huda = $person($this->a, 'employee', 'Huda');
    $this->omar = $person($this->b, 'employee', 'Omar');
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

function meeting(User $organizer, array $people, string $start = '2026-03-02T10:00:00+03:00', string $end = '2026-03-02T11:00:00+03:00', array $extra = []): CalendarEvent
{
    return resolve(SaveEvent::class)->handle(Actor::user($organizer), EventInputData::from([
        'title' => 'Planning', 'startsAt' => $start, 'endsAt' => $end, 'timezone' => 'Asia/Riyadh',
        'required' => array_map(fn (User $u): string => $u->ulid, $people), ...$extra,
    ]));
}

it('invites people with one-tap replies and an .ics, and records their answers', function (): void {
    $event = meeting($this->saud, [$this->rami, $this->huda]);

    expect($event->participants()->pluck('response', 'user_id')->map->value->all())
        ->toBe([$this->saud->id => 'accepted', $this->rami->id => 'pending', $this->huda->id => 'pending']);

    Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->hasTo($this->rami->email) && array_keys($mail->actions) === ['accepted', 'tentative', 'declined'] && str_contains((string) $mail->ics, 'SUMMARY:Planning'));

    $participant = EventParticipant::query()->where('user_id', $this->rami->id)->sole();
    $token = ReplyTokens::make($participant->ulid, Response::Tentative, (string) config('app.key'));

    $this->get(route('calendar-replies.show', $token))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('public/calendar-reply')->where('response', 'tentative'));
    expect($participant->fresh()?->response)->toBe(Response::Tentative);

    $this->get(route('calendar-replies.show', str_replace('.t.', '.a.', $token)))->assertNotFound();
});

it('repeats weekly, and edits or cancels one occurrence or the rest of the series', function (): void {
    $series = meeting($this->saud, [$this->rami], extra: ['recurrence' => ['freq' => 'weekly', 'byweekday' => [1, 3], 'count' => 6]]);
    $range = fn (): array => array_map(fn ($o) => $o->start->setTimezone('Asia/Riyadh')->format('m-d H:i'), Occurrences::forUsers([$this->rami->id], CarbonImmutable::parse('2026-03-01'), CarbonImmutable::parse('2026-04-01'))[$this->rami->id] ?? []);

    expect($range())->toBe(['03-02 10:00', '03-04 10:00', '03-09 10:00', '03-11 10:00', '03-16 10:00', '03-18 10:00']);

    resolve(SaveEvent::class)->handle(Actor::user($this->saud), EventInputData::from([
        'title' => 'Planning (moved)', 'startsAt' => '2026-03-04T14:00:00+03:00', 'endsAt' => '2026-03-04T15:00:00+03:00', 'timezone' => 'Asia/Riyadh', 'required' => [$this->rami->ulid],
    ]), $series, 'this', CarbonImmutable::parse('2026-03-04T07:00:00Z'));
    resolve(CancelEvent::class)->handle(Actor::user($this->saud), $series->fresh() ?? $series, 'this', CarbonImmutable::parse('2026-03-09T07:00:00Z'));

    expect($range())->toBe(['03-02 10:00', '03-04 14:00', '03-11 10:00', '03-16 10:00', '03-18 10:00']);

    resolve(CancelEvent::class)->handle(Actor::user($this->saud), $series->fresh() ?? $series, 'following', CarbonImmutable::parse('2026-03-16T07:00:00Z'));
    expect($range())->toBe(['03-02 10:00', '03-04 14:00', '03-11 10:00']);

    expect(fn () => resolve(CancelEvent::class)->handle(Actor::user($this->rami), $series, 'all'))->toThrow(DomainRuleViolation::class);
});

it('shows free/busy to everyone but titles only within scope, never private ones, and skips declined meetings', function (): void {
    meeting($this->saud, [$this->rami]);
    meeting($this->saud, [$this->rami], '2026-03-02T13:00:00+03:00', '2026-03-02T14:00:00+03:00', ['private' => true]);
    $declined = meeting($this->saud, [$this->rami], '2026-03-02T15:00:00+03:00', '2026-03-02T16:00:00+03:00');
    EventParticipant::query()->where('calendar_event_id', $declined->id)->where('user_id', $this->rami->id)->update(['response' => 'declined']);

    $ask = fn (User $viewer) => $this->actingAs($viewer)->postJson('/api/v1/calendar/availability', [
        'people' => [$this->rami->ulid, $this->huda->ulid], 'from' => '2026-03-02T00:00:00+03:00', 'to' => '2026-03-03T00:00:00+03:00', 'duration' => 60,
    ])->assertOk();

    Sanctum::actingAs($this->omar);
    $outsider = $ask($this->omar)->json();
    expect(collect($outsider['data'][0]['busy'])->pluck('title')->all())->toBe([null, null])
        ->and($outsider['data'][0]['working'][0]['start'])->toBe('2026-03-02T06:00:00+00:00');

    Sanctum::actingAs($this->saud);
    $manager = $ask($this->saud)->json();
    expect(collect($manager['data'][0]['busy'])->pluck('title')->all())->toBe(['Planning', 'Planning'])
        ->and($manager['meta']['suggestions'][0])->toBe(['start' => '2026-03-02T06:00:00+00:00', 'end' => '2026-03-02T07:00:00+00:00'])
        ->and(collect($manager['meta']['suggestions'])->pluck('start'))->not->toContain('2026-03-02T07:00:00+00:00');
});

it('books open slots from a booking page for its audience only, once per slot', function (): void {
    $page = resolve(SaveBookingPage::class)->handle(Actor::user($this->saud), BookingPageInputData::from([
        'title' => ['en' => 'Office hours'], 'durationMinutes' => 30, 'bufferMinutes' => 0, 'timezone' => 'Asia/Riyadh',
        'windows' => [['weekday' => 1, 'from' => '10:00', 'to' => '11:00']], 'startsOn' => '2026-03-01', 'audience' => 'branch', 'branchId' => $this->a->ulid,
    ]));
    meeting($this->saud, [], '2026-03-02T10:30:00+03:00', '2026-03-02T11:00:00+03:00');

    $this->actingAs($this->rami)->get(route('book.show', $page))->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('calendar/book')->where('slots.0.start', '2026-03-02T07:00:00+00:00')->where('slots.1.start', '2026-03-09T07:00:00+00:00'));
    $this->actingAs($this->omar)->get(route('book.show', $page))->assertNotFound();

    $this->actingAs($this->rami)->post(route('book.store', $page), ['start' => '2026-03-02T07:00:00Z'])->assertRedirect();
    expect(fn () => resolve(BookSlot::class)->handle(Actor::user($this->huda), $page, CarbonImmutable::parse('2026-03-02T07:00:00Z')))
        ->toThrow(DomainRuleViolation::class, 'no longer free');

    $booking = CalendarEvent::query()->where('booking_page_id', $page->id)->sole();
    expect($booking->participants()->pluck('user_id')->sort()->values()->all())->toBe(collect([$this->saud->id, $this->rami->id])->sort()->values()->all());
});

it('serves the calendar, events, .ics and replies over HTTP to the people on them only', function (): void {
    $event = meeting($this->saud, [$this->rami]);

    $this->actingAs($this->rami)->get(route('calendar.index', ['date' => '2026-03-02']))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('calendar/index')->where('from', '2026-03-01')->has('events', 1)->where('events.0.myResponse', 'pending'));
    $this->actingAs($this->rami)->get(route('calendar.ics', $event))->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
    $this->actingAs($this->omar)->get(route('calendar.ics', $event))->assertNotFound();
    $this->actingAs($this->omar)->get(route('calendar.show', $event))->assertNotFound();

    $this->actingAs($this->rami)->post(route('calendar.respond', $event), ['response' => 'accepted'])->assertRedirect();
    $this->actingAs($this->rami)->put(route('calendar.update', $event), ['title' => 'Mine now', 'startsAt' => '2026-03-02T10:00:00+03:00', 'endsAt' => '2026-03-02T11:00:00+03:00', 'timezone' => 'Asia/Riyadh'])->assertForbidden();

    Sanctum::actingAs($this->rami);
    $this->getJson('/api/v1/calendar/events?from=2026-03-01&to=2026-03-07')->assertOk()->assertJsonPath('data.0.myResponse', 'accepted');
    $this->getJson('/api/v1/calendar/people?search=hud')->assertOk()->assertJsonPath('data.0.id', $this->huda->ulid);
});

it('takes a WhatsApp Going / Maybe / Not going button only from the invitee\'s own number', function (): void {
    meeting($this->saud, [$this->rami, $this->huda]);
    $participant = EventParticipant::query()->where('user_id', $this->rami->id)->sole();
    $token = ReplyTokens::make($participant->ulid, Response::Declined, (string) config('app.key'));
    WhatsAppContact::query()->create(['user_id' => $this->rami->id, 'phone_e164' => '+966500000001', 'verified_at' => now()]);
    WhatsAppContact::query()->create(['user_id' => $this->huda->id, 'phone_e164' => '+966500000002', 'verified_at' => now()]);

    resolve(HandleWhatsAppReply::class)->handle(IntegrationType::WhatsAppOfficial, '966500000002', 'mawred:'.$token);
    expect($participant->fresh()?->response)->toBe(Response::Pending);

    resolve(HandleWhatsAppReply::class)->handle(IntegrationType::WhatsAppOfficial, '966500000001', 'mawred:'.$token);
    expect($participant->fresh()?->response)->toBe(Response::Declined);
});
