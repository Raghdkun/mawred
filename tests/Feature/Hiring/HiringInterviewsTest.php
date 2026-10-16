<?php

declare(strict_types=1);

use App\Domain\Access\Models\User;
use App\Domain\Calendar\Enums\EventKind;
use App\Domain\Calendar\Models\CalendarEvent;
use App\Domain\Hiring\Actions\MoveApplication;
use App\Domain\Hiring\Actions\RejectApplication;
use App\Domain\Hiring\Actions\SaveStageAutomation;
use App\Domain\Hiring\Actions\ScheduleInterview;
use App\Domain\Hiring\Actions\SeedHiringDefaults;
use App\Domain\Hiring\Actions\SubmitScorecard;
use App\Domain\Hiring\Data\InterviewInputData;
use App\Domain\Hiring\Enums\ApplicationSource;
use App\Domain\Hiring\Enums\InterviewStatus;
use App\Domain\Hiring\Enums\MessageDirection;
use App\Domain\Hiring\Enums\MessagePurpose;
use App\Domain\Hiring\Enums\Recommendation;
use App\Domain\Hiring\Enums\RejectionReason;
use App\Domain\Hiring\Mail\CandidateMail;
use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\CandidateMessage;
use App\Domain\Hiring\Models\Interview;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Models\PipelineStage;
use App\Domain\Hiring\Models\ScorecardCriterion;
use App\Domain\Hiring\Support\CandidateLinks;
use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Events\WhatsAppMessageReceived;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Actions\SeedPolicyDefaults;
use App\Domain\Requests\Actions\SeedRequestTypes;
use App\Domain\Setup\Support\Settings;
use App\Support\Actors\Actor;
use App\Support\Crypto\SignedTokens;
use App\Support\Errors\DomainRuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Phase 6b: interviews with panels and self-booking (D89, D92), blind scorecards (D82, D90),
| stage automations and the rejection message (D93), and the communications log (D94).
*/
beforeEach(function (): void {
    Mail::fake();
    seedAccess();
    resolve(SeedPolicyDefaults::class)->handle();
    resolve(SeedRequestTypes::class)->handle();
    resolve(SeedHiringDefaults::class)->handle();

    $this->branch = Branch::factory()->riyadh()->create();
    $this->recruiter = userWithRoles('recruiter');
    $colleague = function (string $name): User {
        $user = userWithRoles('employee');
        $user->forceFill(['name' => $name, 'timezone' => 'Asia/Riyadh'])->save();
        Employee::factory()->placedIn($this->branch)->create(['user_id' => $user->id, 'name_en' => $name, 'hire_date' => '2024-01-01']);

        return $user;
    };
    $this->panelist = $colleague('Pat Panel');
    $this->second = $colleague('Sam Second');
    $this->outsider = $colleague('Olly Outside');

    $this->requisition = JobRequisition::factory()->create(['branch_id' => $this->branch->id, 'title' => ['en' => 'Barista', 'ar' => 'باريستا']]);
    hiringSixBBoard($this->requisition);
    $this->candidate = Candidate::factory()->create(['name' => 'Lina Haddad', 'email' => 'lina@example.com', 'phone' => '+966500000001']);
    $this->application = JobApplication::query()->create([
        'candidate_id' => $this->candidate->id,
        'job_requisition_id' => $this->requisition->id,
        'pipeline_stage_id' => $this->requisition->stages()->firstOrFail()->id,
        'source' => ApplicationSource::Careers,
        'applied_at' => now(), 'stage_entered_at' => now(), 'last_activity_at' => now(),
    ]);
});

function hiringSixBBoard(JobRequisition $requisition): void
{
    foreach (PipelineStage::query()->whereNull('job_requisition_id')->orderBy('position')->get() as $template) {
        PipelineStage::query()->create(['job_requisition_id' => $requisition->id, 'name' => $template->getTranslations('name'), 'kind' => $template->kind, 'position' => $template->position, 'automation' => $template->automation]);
    }

    foreach (ScorecardCriterion::query()->whereNull('job_requisition_id')->orderBy('position')->get() as $template) {
        ScorecardCriterion::query()->create(['job_requisition_id' => $requisition->id, 'name' => $template->getTranslations('name'), 'position' => $template->position]);
    }
}

function hiringSixBInterview(object $test, ?string $startsAt = null, array $panel = []): Interview
{
    return resolve(ScheduleInterview::class)->handle(Actor::user($test->recruiter), $test->application, InterviewInputData::from([
        'title' => 'First interview',
        'durationMinutes' => 30,
        'panel' => $panel === [] ? [$test->panelist->ulid, $test->second->ulid] : $panel,
        'timezone' => 'Asia/Riyadh',
        'startsAt' => $startsAt,
    ]));
}

function hiringSixBRatings(Interview $interview, int $rating): array
{
    $criteria = ScorecardCriterion::query()->where('job_requisition_id', $interview->application()->firstOrFail()->job_requisition_id)->pluck('ulid');

    return $criteria->mapWithKeys(fn (string $ulid): array => [$ulid => ['rating' => $rating, 'note' => null]])->all();
}

it('sends a booking link and books the time the candidate picks from the panel’s free time', function (): void {
    $interview = hiringSixBInterview($this);

    expect($interview->status)->toBe(InterviewStatus::AwaitingBooking)
        ->and(CandidateMessage::query()->sole())->purpose->toBe(MessagePurpose::Booking)->direction->toBe(MessageDirection::Out);
    Mail::assertQueued(CandidateMail::class, fn (CandidateMail $mail): bool => $mail->hasTo('lina@example.com') && collect($mail->buttons)->contains('url', CandidateLinks::booking($interview)));

    $token = SignedTokens::make(CandidateLinks::BOOKING, $interview->ulid);
    $slots = $this->get(route('careers.interviews.show', $token))
        ->assertInertia(fn (Assert $page): Assert => $page->component('public/interview-booking')->where('open', true)->has('slots'))
        ->viewData('page')['props']['slots'];

    expect($slots)->not->toBeEmpty();

    // A forged or other-purpose token opens nothing.
    $this->get(route('careers.interviews.show', $interview->ulid.'.0000'))->assertNotFound();
    $this->get(route('careers.interviews.show', SignedTokens::make(CandidateLinks::OFFER, $interview->ulid)))->assertNotFound();

    $this->post(route('careers.interviews.book', $token), ['start' => $slots[0]])->assertRedirect();
    $interview->refresh();
    $event = CalendarEvent::query()->sole();

    expect($interview->status)->toBe(InterviewStatus::Scheduled)
        ->and($interview->starts_at?->toIso8601String())->toBe(CarbonImmutable::parse($slots[0])->utc()->toIso8601String())
        ->and($event->kind)->toBe(EventKind::Interview)
        ->and($event->participants()->pluck('user_id')->sort()->values()->all())->toBe(collect([$this->recruiter->id, $this->panelist->id, $this->second->id])->sort()->values()->all())
        ->and(CandidateMessage::query()->where('purpose', MessagePurpose::Interview)->count())->toBe(1);
    Mail::assertQueued(CandidateMail::class, fn (CandidateMail $mail): bool => $mail->ics !== null);

    // Picking again once booked is refused.
    $this->post(route('careers.interviews.book', $token), ['start' => $slots[1] ?? $slots[0]])->assertStatus(410);
});

it('refuses a time the panel is no longer free for', function (): void {
    $interview = hiringSixBInterview($this);
    $token = SignedTokens::make(CandidateLinks::BOOKING, $interview->ulid);

    // Two days ahead at 03:00 local: outside working hours.
    $this->post(route('careers.interviews.book', $token), ['start' => CarbonImmutable::now('Asia/Riyadh')->addDays(2)->setTime(3, 0)->toIso8601String()])
        ->assertSessionHasErrors('error');

    expect($interview->fresh()?->status)->toBe(InterviewStatus::AwaitingBooking);
});

it('lets a panelist see only that candidate and give blind feedback', function (): void {
    $interview = hiringSixBInterview($this, CarbonImmutable::now()->addDay()->setTime(9, 0)->toIso8601String());

    $this->actingAs($this->outsider)->get(route('hiring.candidates.show', $this->candidate))->assertNotFound();
    $this->actingAs($this->panelist)->get(route('hiring.requisitions.index'))->assertForbidden();
    $this->actingAs($this->panelist)->get(route('hiring.candidates.show', $this->candidate))
        ->assertInertia(fn (Assert $page): Assert => $page->component('hiring/candidate')
            ->where('messages', null)
            ->where('can.see', false)
            ->has('applications', 1)
            ->where('applications.0.events', [])
            ->where('applications.0.offers', [])
            ->where('applications.0.interviews.0.can.score', true)
            ->where('applications.0.interviews.0.scorecards', []));
    $this->actingAs($this->panelist)->get(route('hiring.interviews.index'))
        ->assertInertia(fn (Assert $page): Assert => $page->component('hiring/interviews')->where('interviews.0.scorecard', 'upcoming'));

    // The second panelist submits first; the first can't see it until they submit too.
    resolve(SubmitScorecard::class)->handle(Actor::user($this->second), $interview, hiringSixBRatings($interview, 4), Recommendation::StrongYes, 'Great', true);
    $this->get(route('hiring.candidates.show', $this->candidate))->assertInertia(fn (Assert $page): Assert => $page->where('applications.0.interviews.0.scorecards', [])->where('applications.0.interviews.0.submitted', 1));

    expect(fn () => resolve(SubmitScorecard::class)->handle(Actor::user($this->panelist), $interview, [], null, null, true))->toThrow(DomainRuleViolation::class)
        ->and(fn () => resolve(SubmitScorecard::class)->handle(Actor::user($this->outsider), $interview, hiringSixBRatings($interview, 2), Recommendation::No, null, true))->toThrow(DomainRuleViolation::class);

    $this->put(route('hiring.interviews.scorecard', $interview), ['ratings' => hiringSixBRatings($interview, 2), 'recommendation' => 'no', 'comment' => 'Not yet', 'submit' => true])->assertRedirect();

    $this->get(route('hiring.candidates.show', $this->candidate))->assertInertia(fn (Assert $page): Assert => $page
        ->where('applications.0.interviews.0.scorecards.0.recommendation', 'strong_yes')
        ->where('applications.0.score', 3));
    expect($this->application->fresh()?->score)->toBe('3.00')
        ->and(fn () => resolve(SubmitScorecard::class)->handle(Actor::user($this->panelist), $interview, hiringSixBRatings($interview, 3), Recommendation::Yes, null, true))->toThrow(DomainRuleViolation::class);

    // The hiring team reads every scorecard any time; the outsider still nothing.
    $this->actingAs($this->recruiter)->get(route('hiring.candidates.show', $this->candidate))->assertInertia(fn (Assert $page): Assert => $page->has('applications.0.interviews.0.scorecards', 2));
    $this->actingAs($this->outsider)->put(route('hiring.interviews.scorecard', $interview), ['ratings' => [], 'submit' => false])->assertNotFound();
});

it('runs stage automations when a candidate moves', function (): void {
    $interviewStage = PipelineStage::query()->where('job_requisition_id', $this->requisition->id)->where('kind', 'interview')->sole();
    resolve(SaveStageAutomation::class)->handle(Actor::user($this->recruiter), $interviewStage, [
        'message' => ['enabled' => true, 'subject' => ['en' => 'Next step for {{ job_title }}'], 'body' => ['en' => 'Hi {{ candidate_name }}, you are in {{ stage }}.']],
        'booking' => ['enabled' => true, 'title' => 'Panel interview', 'duration' => 45, 'panel' => [$this->panelist->ulid]],
    ]);

    expect(fn () => resolve(SaveStageAutomation::class)->handle(Actor::user($this->recruiter), $interviewStage, [
        'message' => ['enabled' => true, 'subject' => ['en' => '{{ salary }}'], 'body' => ['en' => 'x']],
        'booking' => ['enabled' => false, 'title' => '', 'duration' => 30, 'panel' => []],
    ]))->toThrow(DomainRuleViolation::class);

    resolve(MoveApplication::class)->handle(Actor::user($this->recruiter), $this->application, $interviewStage);

    expect(CandidateMessage::query()->where('purpose', MessagePurpose::Stage)->sole()->body)->toBe('Hi Lina Haddad, you are in Interview.')
        ->and(Interview::query()->sole())->title->toBe('Panel interview')->duration_minutes->toBe(45)->status->toBe(InterviewStatus::AwaitingBooking);
});

it('thanks rejected candidates when the message is on and logs WhatsApp replies', function (): void {
    resolve(Settings::class)->set('hiring.rejection_message', ['enabled' => true, 'subject' => ['en' => 'Your application', 'ar' => 'طلبك'], 'body' => ['en' => 'Thank you, {{ candidate_name }}.', 'ar' => 'شكرًا {{ candidate_name }}.']]);

    resolve(RejectApplication::class)->handle(Actor::user($this->recruiter), $this->application, RejectionReason::NotQualified, notify: true);

    expect(CandidateMessage::query()->where('purpose', MessagePurpose::Rejection)->sole()->body)->toBe('Thank you, Lina Haddad.');

    event(new WhatsAppMessageReceived(IntegrationType::WhatsAppWaha, '966500000001', 'Thanks for letting me know'));
    event(new WhatsAppMessageReceived(IntegrationType::WhatsAppWaha, '966599999999', 'Who is this?'));

    expect(CandidateMessage::query()->where('direction', MessageDirection::In)->sole())->body->toBe('Thanks for letting me know')->candidate_id->toBe($this->candidate->id);
});

it('writes to a candidate from the profile and logs failures', function (): void {
    $this->actingAs($this->recruiter)->post(route('hiring.candidates.messages', $this->candidate), ['channel' => 'email', 'subject' => 'Hello', 'body' => 'Can we talk?'])->assertRedirect();
    $this->post(route('hiring.candidates.messages', $this->candidate), ['channel' => 'whatsapp', 'body' => 'Hi'])->assertRedirect();

    expect(CandidateMessage::query()->where('channel', 'email')->sole()->status->value)->toBe('sent')
        ->and(CandidateMessage::query()->where('channel', 'whatsapp')->sole())->status->value->toBe('failed');

    $this->get(route('hiring.candidates.show', $this->candidate))->assertInertia(fn (Assert $page): Assert => $page->has('messages', 2));
    $this->actingAs($this->panelist)->post(route('hiring.candidates.messages', $this->candidate), ['channel' => 'email', 'subject' => 'x', 'body' => 'x'])->assertNotFound();
});
