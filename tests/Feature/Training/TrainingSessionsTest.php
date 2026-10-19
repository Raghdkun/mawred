<?php

declare(strict_types=1);

use App\Domain\Calendar\Enums\EventKind;
use App\Domain\Calendar\Models\CalendarEvent;
use App\Domain\Calendar\Models\EventParticipant;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Training\Actions\ChangeCourseStatus;
use App\Domain\Training\Actions\Enrol;
use App\Domain\Training\Actions\SaveCourse;
use App\Domain\Training\Actions\SaveCourseModules;
use App\Domain\Training\Actions\SaveSession;
use App\Domain\Training\Actions\SeedTrainingDefaults;
use App\Domain\Training\Data\CourseInputData;
use App\Domain\Training\Data\ModulesInputData;
use App\Domain\Training\Data\SessionInputData;
use App\Domain\Training\Enums\BookingStatus;
use App\Domain\Training\Enums\CourseStatus;
use App\Domain\Training\Enums\EnrollmentSource;
use App\Domain\Training\Enums\EnrollmentStatus;
use App\Domain\Training\Models\Enrollment;
use App\Domain\Training\Models\SessionBooking;
use App\Domain\Training\Models\TrainingSession;
use App\Domain\Training\Support\CheckInCodes;
use App\Support\Actors\Actor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Phase 7b: sessions with seats and the waitlist (D107), attendance by rotating QR or the roster
| (D101), completion needing one attended session (D108), and feedback (D109).
*/
beforeEach(function (): void {
    Mail::fake();
    seedAccess();
    resolve(SeedTrainingDefaults::class)->handle();
    $branch = Branch::factory()->riyadh()->create();
    $this->trainer = userWithRoles('trainer');
    $this->people = collect(range(1, 3))->map(function (int $i) use ($branch): array {
        $user = userWithRoles('employee');
        $employee = Employee::factory()->placedIn($branch)->create(['user_id' => $user->id]);

        return [$user, $employee];
    });

    $course = resolve(SaveCourse::class)->handle(Actor::user($this->trainer), CourseInputData::from(['title' => ['en' => 'Barista class'], 'validityMonths' => 12]));
    resolve(SaveCourseModules::class)->handle(Actor::user($this->trainer), $course, ModulesInputData::from(['modules' => [['kind' => 'text', 'title' => ['en' => 'Before class']]]]));
    $this->course = resolve(ChangeCourseStatus::class)->handle(Actor::user($this->trainer), $course->fresh() ?? $course, CourseStatus::Published);
    $this->starts = CarbonImmutable::now()->addMinutes(30)->startOfMinute();
    $this->session = resolve(SaveSession::class)->handle(Actor::user($this->trainer), $this->course, SessionInputData::from([
        'startsAt' => $this->starts->toIso8601String(), 'endsAt' => $this->starts->addHours(2)->toIso8601String(), 'timezone' => 'Asia/Riyadh',
        'location' => 'Training room', 'capacity' => 1, 'trainerId' => $this->trainer->ulid,
    ]));
    $this->enrollments = $this->people->map(fn (array $p): Enrollment => resolve(Enrol::class)->handle(Actor::user($this->trainer), $p[1], $this->course, EnrollmentSource::Assigned));
});

function trainingBook(object $test, int $who): void
{
    [$user] = $test->people[$who];
    $test->actingAs($user)->post(route('training.sessions.book', [$test->course, $test->session]))->assertRedirect();
}

it('books seats, keeps a waitlist and promotes the first in line', function (): void {
    $event = CalendarEvent::query()->sole();
    expect($event->kind)->toBe(EventKind::Training)->and($event->organizer_user_id)->toBe($this->trainer->id);

    trainingBook($this, 0);
    trainingBook($this, 1);
    trainingBook($this, 2);
    [$first, $second, $third] = $this->enrollments->map(fn (Enrollment $e): SessionBooking => SessionBooking::query()->where('enrollment_id', $e->id)->sole())->all();

    expect($first->status)->toBe(BookingStatus::Booked)
        ->and($second->status)->toBe(BookingStatus::Waitlisted)
        ->and($third->status)->toBe(BookingStatus::Waitlisted)
        ->and(EventParticipant::query()->where('calendar_event_id', $event->id)->pluck('user_id')->all())->toContain($this->people[0][0]->id);

    $this->actingAs($this->people[1][0])->get(route('training.courses.show', $this->course))
        ->assertInertia(fn (Assert $page): Assert => $page->where('sessions.0.mine.status', 'waitlisted')->where('sessions.0.mine.position', 1)->where('sessions.0.meetingUrl', null));

    // The booked learner gives up the seat: the first in line gets it and the calendar invite.
    $this->actingAs($this->people[0][0])->delete(route('training.bookings.cancel', $first))->assertRedirect();
    expect($second->fresh()?->status)->toBe(BookingStatus::Booked)
        ->and($third->fresh()?->status)->toBe(BookingStatus::Waitlisted)
        ->and(EventParticipant::query()->where('calendar_event_id', $event->id)->pluck('user_id')->all())->toContain($this->people[1][0]->id)->not->toContain($this->people[0][0]->id);

    // More seats move the rest up; the trainer sees the roster.
    resolve(SaveSession::class)->handle(Actor::user($this->trainer), $this->course, SessionInputData::from([
        'startsAt' => $this->starts->toIso8601String(), 'endsAt' => $this->starts->addHours(2)->toIso8601String(), 'timezone' => 'Asia/Riyadh', 'capacity' => 5,
    ]), $this->session);
    expect($third->fresh()?->status)->toBe(BookingStatus::Booked);
    $this->actingAs($this->trainer)->get(route('training.manage.sessions.show', $this->session))
        ->assertInertia(fn (Assert $page): Assert => $page->component('training/manage/session')->has('roster', 3)->where('session.booked', 2));
    $this->actingAs($this->people[0][0])->get(route('training.manage.sessions.show', $this->session))->assertForbidden();
});

it('checks people in with the rotating QR and completes the course after one session', function (): void {
    [$user, $employee] = $this->people[0];
    $enrollment = $this->enrollments[0];
    $this->actingAs($user)->post(route('training.learn.complete', [$this->course, $this->course->modules()->sole()]));
    expect($enrollment->fresh())->status->toBe(EnrollmentStatus::Enrolled)->progress->toBe(50);

    // An old code is refused; the current one checks in, booked or not.
    $old = CheckInCodes::code($this->session, now()->subMinutes(2)->getTimestamp());
    $this->post(route('training.check-in.confirm'), ['code' => $old])->assertSessionHasErrors('error');
    $code = CheckInCodes::code($this->session, now()->getTimestamp());
    $this->get(route('training.check-in', ['code' => $code]))->assertInertia(fn (Assert $page): Assert => $page->component('training/check-in')->where('session.course', 'Barista class'));
    $this->post(route('training.check-in.confirm'), ['code' => $code])->assertRedirect(route('training.courses.show', $this->course));

    expect(SessionBooking::query()->where('enrollment_id', $enrollment->id)->sole())->check_in->toBe('qr')->attended_at->not->toBeNull()
        ->and($enrollment->fresh())->status->toBe(EnrollmentStatus::Completed)->progress->toBe(100);

    // Someone not on the course can't check in; the trainer can tick a learner off by hand.
    $outsider = userWithRoles('employee');
    $this->actingAs($outsider)->post(route('training.check-in.confirm'), ['code' => $code])->assertSessionHasErrors('error');
    trainingBook($this, 1);
    $booking = SessionBooking::query()->where('enrollment_id', $this->enrollments[1]->id)->sole();
    $this->actingAs($this->trainer)->put(route('training.manage.bookings.attendance', $booking), ['attended' => true])->assertRedirect();
    expect($booking->fresh())->check_in->toBe('manual')->attended_at->not->toBeNull();

    // The display polls a fresh code; the learner who finished rates the course.
    $this->getJson(route('training.manage.sessions.code', $this->session))->assertOk()->assertJsonPath('attended', 2);
    $this->actingAs($user)->post(route('training.feedback', $enrollment), ['rating' => 4, 'comment' => 'Great class'])->assertRedirect();
    $this->actingAs($this->trainer)->get(route('training.manage.courses.people', $this->course))
        ->assertInertia(fn (Assert $page): Assert => $page->where('feedback.count', 1)->where('feedback.average', 4)->where('feedback.comments.0.comment', 'Great class'));
    $this->actingAs($this->people[2][0])->post(route('training.feedback', $this->enrollments[0]), ['rating' => 1])->assertNotFound();
    $this->post(route('training.feedback', $this->enrollments[2]), ['rating' => 5])->assertSessionHasErrors('error');
});

it('cancels a session and releases every seat', function (): void {
    trainingBook($this, 0);
    trainingBook($this, 1);
    $this->actingAs($this->trainer)->delete(route('training.manage.sessions.cancel', $this->session))->assertRedirect();

    expect(TrainingSession::query()->sole()->status->value)->toBe('cancelled')
        ->and(SessionBooking::query()->where('status', '!=', 'cancelled')->count())->toBe(0)
        ->and(CalendarEvent::query()->sole()->cancelled_at)->not->toBeNull();
});
