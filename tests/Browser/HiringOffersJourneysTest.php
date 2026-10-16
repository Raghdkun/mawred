<?php

declare(strict_types=1);

use App\Domain\Hiring\Actions\SaveOffer;
use App\Domain\Hiring\Actions\ScheduleInterview;
use App\Domain\Hiring\Actions\SeedHiringDefaults;
use App\Domain\Hiring\Data\InterviewInputData;
use App\Domain\Hiring\Data\OfferInputData;
use App\Domain\Hiring\Enums\ApplicationSource;
use App\Domain\Hiring\Enums\ApplicationStatus;
use App\Domain\Hiring\Enums\InterviewStatus;
use App\Domain\Hiring\Enums\OfferStatus;
use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\Interview;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Models\Offer;
use App\Domain\Hiring\Models\PipelineStage;
use App\Domain\Hiring\Models\ScorecardCriterion;
use App\Domain\Hiring\Support\CandidateLinks;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Actions\SeedPolicyDefaults;
use App\Domain\Requests\Actions\SeedRequestTypes;
use App\Support\Actors\Actor;
use App\Support\Crypto\SignedTokens;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/*
| Phase 6b journeys (EN + AR, axe): the candidate books an interview from their link, a panelist
| fills in the scorecard, the candidate signs the offer, and HR confirms the hire.
*/
beforeEach(function (): void {
    Storage::fake('local');
    Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.4 signed letter', 200, ['Content-Type' => 'application/pdf'])]);
    seedAccess();
    resolve(SeedPolicyDefaults::class)->handle();
    resolve(SeedRequestTypes::class)->handle();
    resolve(SeedHiringDefaults::class)->handle();

    $this->branch = Branch::factory()->riyadh()->create(['name' => ['en' => 'Riyadh HQ', 'ar' => 'المقر الرئيسي']]);
    $this->recruiter = userWithRoles('recruiter');
    $this->panelist = userWithRoles('employee');
    $this->panelist->forceFill(['name' => 'Pat Panel', 'timezone' => 'Asia/Riyadh'])->save();
    Employee::factory()->placedIn($this->branch)->create(['user_id' => $this->panelist->id, 'name_en' => 'Pat Panel', 'hire_date' => '2024-01-01']);

    $this->requisition = JobRequisition::factory()->create(['branch_id' => $this->branch->id, 'headcount' => 1, 'title' => ['en' => 'Barista', 'ar' => 'باريستا']]);
    foreach (PipelineStage::query()->whereNull('job_requisition_id')->orderBy('position')->get() as $template) {
        PipelineStage::query()->create(['job_requisition_id' => $this->requisition->id, 'name' => $template->getTranslations('name'), 'kind' => $template->kind, 'position' => $template->position]);
    }
    foreach (ScorecardCriterion::query()->whereNull('job_requisition_id')->orderBy('position')->get() as $template) {
        ScorecardCriterion::query()->create(['job_requisition_id' => $this->requisition->id, 'name' => $template->getTranslations('name'), 'position' => $template->position]);
    }

    $this->candidate = Candidate::factory()->create(['name' => 'Lina Haddad', 'email' => 'lina@example.com', 'phone' => '+966500000001']);
    $this->application = JobApplication::query()->create([
        'candidate_id' => $this->candidate->id, 'job_requisition_id' => $this->requisition->id,
        'pipeline_stage_id' => $this->requisition->stages()->where('kind', 'interview')->firstOrFail()->id,
        'source' => ApplicationSource::Careers, 'applied_at' => now(), 'stage_entered_at' => now(), 'last_activity_at' => now(),
    ]);
});

function hiringBrowserInterview(object $test, ?string $startsAt = null): Interview
{
    return resolve(ScheduleInterview::class)->handle(Actor::user($test->recruiter), $test->application, InterviewInputData::from([
        'title' => 'First interview', 'durationMinutes' => 30, 'panel' => [$test->panelist->ulid], 'timezone' => 'Asia/Riyadh', 'startsAt' => $startsAt,
    ]));
}

/** An offer the hiring manager and HR already approved and sent (the approval chain has its own feature test). */
function hiringBrowserOffer(object $test, OfferStatus $status): Offer
{
    $offer = resolve(SaveOffer::class)->handle(Actor::user($test->recruiter), $test->application, OfferInputData::from([
        'jobTitle' => 'Senior Barista',
        'startOn' => CarbonImmutable::now()->addMonth()->toDateString(),
        'salary' => ['amount_minor' => 850000, 'currency' => 'SAR'],
        'branchId' => $test->branch->ulid,
        'expiresOn' => CarbonImmutable::now()->addWeeks(2)->toDateString(),
    ]));
    $offer->forceFill(['status' => $status, 'sent_at' => now()])->save();

    return $offer;
}

it('books an interview from the candidate’s link', function (string $locale): void {
    $interview = hiringBrowserInterview($this);
    $token = SignedTokens::make(CandidateLinks::BOOKING, $interview->ulid);

    $page = visit(route('careers.interviews.show', $token, false)."?lang={$locale}")->on()->mobile()->wait(1)->assertNoAccessibilityIssues();
    $first = $page->script("document.querySelector('[data-booking-slot]').dataset.bookingSlot");

    $page->click("[data-booking-slot=\"{$first}\"]")
        ->click('[data-confirm-slot]')
        ->wait(1.5)
        ->assertPresent('[data-booked]')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    expect($interview->fresh()?->starts_at?->toIso8601String())->toBe(CarbonImmutable::parse($first)->toIso8601String());
    expect($interview->fresh())->status->toBe(InterviewStatus::Scheduled)->starts_at->not->toBeNull();
})->with(['en', 'ar']);

it('lets a panelist fill in the scorecard', function (string $locale): void {
    $interview = hiringBrowserInterview($this, CarbonImmutable::now()->subHour()->toIso8601String());
    $this->panelist->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->panelist);

    $page = visit("/hiring/candidates/{$this->candidate->ulid}")->on()->macbook14()->wait(1)->assertNoAccessibilityIssues();

    foreach (ScorecardCriterion::query()->where('job_requisition_id', $this->requisition->id)->pluck('ulid') as $criterion) {
        $page->click("[data-rate=\"{$criterion}-3\"]");
    }

    $page->click('[data-recommend="yes"]')
        ->click('[data-submit-scorecard]')
        ->wait(1.2)
        ->assertMissing('[data-scorecard-form]')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    expect($interview->scorecards()->sole())->submitted_at->not->toBeNull()->recommendation->value->toBe('yes');
})->with(['en', 'ar']);

it('lets the candidate sign the offer', function (string $locale): void {
    $this->candidate->forceFill(['locale' => $locale])->save();
    $offer = hiringBrowserOffer($this, OfferStatus::Sent);
    $token = SignedTokens::make(CandidateLinks::OFFER, $offer->ulid);

    visit(route('careers.offers.show', $token, false)."?lang={$locale}")
        ->on()->macbook14()
        ->wait(1)
        ->assertNoAccessibilityIssues()
        ->fill('#signed-name', 'Lina Haddad')
        ->drag('[data-signature-pad]', '[data-agree]')
        ->click('[data-agree]')
        ->click('[data-accept-offer]')
        ->wait(2)
        ->assertPresent('[data-offer-result]')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    expect($offer->fresh())->status->toBe(OfferStatus::Accepted)->signed_name->toBe('Lina Haddad');
})->with(['en', 'ar']);

it('lets HR confirm the hire once the offer is accepted', function (string $locale): void {
    $offer = hiringBrowserOffer($this, OfferStatus::Accepted);
    $this->application->forceFill(['pipeline_stage_id' => $this->requisition->stages()->where('kind', 'offer')->sole()->id])->save();
    $hr = userWithRoles('hr_admin');
    $hr->forceFill(['locale' => $locale, 'two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
    $this->actingAs($hr);

    visit("/hiring/candidates/{$this->candidate->ulid}")
        ->on()->macbook14()
        ->wait(1)
        ->click('[data-hire]')
        ->wait(0.6)
        ->assertNoAccessibilityIssues()
        ->click('[data-confirm-hire]')
        ->wait(2)
        ->assertNoJavaScriptErrors();

    $employee = Employee::query()->where('name_en', 'Lina Haddad')->sole();
    expect($this->application->fresh())->status->toBe(ApplicationStatus::Hired)->employee_id->toBe($employee->id)
        ->and($employee->hire_date->toDateString())->toBe($offer->start_on->toDateString());
})->with(['en', 'ar']);

/*
 * Visual check (handoff 32–35, D32): 390 / 834 / 1440 × LTR / RTL; the offer page ★ also in dark.
 */
it('captures the 6b screens for review', function (string $locale): void {
    $this->candidate->forceFill(['locale' => $locale])->save();
    hiringBrowserInterview($this, CarbonImmutable::now()->subHour()->toIso8601String());
    $booking = hiringBrowserInterview($this);
    $offer = hiringBrowserOffer($this, OfferStatus::Sent);
    $this->recruiter->forceFill(['locale' => $locale])->save();
    $this->panelist->forceFill(['locale' => $locale])->save();

    $shots = [
        'booking' => [null, route('careers.interviews.show', SignedTokens::make(CandidateLinks::BOOKING, $booking->ulid), false)."?lang={$locale}"],
        'offer' => [null, route('careers.offers.show', SignedTokens::make(CandidateLinks::OFFER, $offer->ulid), false)."?lang={$locale}"],
        'candidate' => [$this->recruiter, "/hiring/candidates/{$this->candidate->ulid}"],
        'interviews' => [$this->panelist, '/hiring/interviews'],
        'settings' => [$this->recruiter, '/hiring/settings'],
    ];

    foreach ($shots as $name => [$user, $path]) {
        if ($user !== null) {
            $this->actingAs($user);
        }

        visit($path)->on()->mobile()->wait(1)->screenshot(true, "hiring6b-{$name}-{$locale}-390");
        visit($path)->on()->iPadPro()->wait(1)->screenshot(true, "hiring6b-{$name}-{$locale}-834");
        visit($path)->on()->macbook14()->wait(1)->screenshot(true, "hiring6b-{$name}-{$locale}-1440");
    }

    visit($shots['offer'][1])->on()->macbook14()->inDarkMode()->wait(1)->screenshot(true, "hiring6b-offer-{$locale}-dark");
})->with(['en', 'ar'])->skip(fn (): bool => ! env('SCREENSHOTS'), 'Set SCREENSHOTS=1 to capture.');
