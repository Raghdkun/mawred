<?php

declare(strict_types=1);

use App\Domain\Access\Models\User;
use App\Domain\Hiring\Actions\AnonymiseCandidate;
use App\Domain\Hiring\Actions\HireCandidate;
use App\Domain\Hiring\Actions\PurgeCandidates;
use App\Domain\Hiring\Actions\SaveOffer;
use App\Domain\Hiring\Actions\SeedHiringDefaults;
use App\Domain\Hiring\Actions\SendOffer;
use App\Domain\Hiring\Actions\SubmitOffer;
use App\Domain\Hiring\Data\HireCandidateData;
use App\Domain\Hiring\Data\OfferInputData;
use App\Domain\Hiring\Enums\ApplicationSource;
use App\Domain\Hiring\Enums\ApplicationStatus;
use App\Domain\Hiring\Enums\MessageChannel;
use App\Domain\Hiring\Enums\OfferStatus;
use App\Domain\Hiring\Enums\RequisitionStatus;
use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\CandidateMessage;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Models\Offer;
use App\Domain\Hiring\Models\PipelineStage;
use App\Domain\Hiring\Support\CandidateLinks;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Enums\EmployeeStatus;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\EmploymentHistoryEntry;
use App\Domain\Policies\Actions\SeedPolicyDefaults;
use App\Domain\Requests\Actions\SeedRequestTypes;
use App\Domain\Requests\Support\RequestPresenter;
use App\Domain\Workflows\Actions\SeedChecklists;
use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Enums\Decision;
use App\Domain\Workflows\Enums\TaskStatus;
use App\Domain\Workflows\Models\WorkflowTask;
use App\Support\Actors\Actor;
use App\Support\Crypto\SignedTokens;
use App\Support\Errors\DomainRuleViolation;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Phase 6b: offers drafted from the template (D95), approved by the hiring manager then HR (D91),
| signed by the candidate (D83), the hire (D85), and retention (D86, D96).
*/
beforeEach(function (): void {
    Mail::fake();
    Storage::fake('local');
    Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.4 signed letter', 200, ['Content-Type' => 'application/pdf'])]);
    seedAccess();
    resolve(SeedPolicyDefaults::class)->handle();
    resolve(SeedRequestTypes::class)->handle();
    resolve(SeedChecklists::class)->handle();
    resolve(SeedHiringDefaults::class)->handle();

    $this->branch = Branch::factory()->riyadh()->create(['name' => ['en' => 'Riyadh HQ', 'ar' => 'المقر الرئيسي']]);
    $this->recruiter = userWithRoles('recruiter');
    $this->managerUser = userWithRoles('branch_manager');
    $this->manager = Employee::factory()->placedIn($this->branch)->create(['user_id' => $this->managerUser->id, 'name_en' => 'Mona Manager']);
    $this->hr = userWithRoles('hr_admin');
    $this->hr->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();

    $this->requisition = JobRequisition::factory()->create(['branch_id' => $this->branch->id, 'headcount' => 1, 'hiring_manager_employee_id' => $this->manager->id]);
    foreach (SeedHiringDefaults::stages() as $position => [$kind, $en, $ar]) {
        PipelineStage::query()->create(['job_requisition_id' => $this->requisition->id, 'name' => ['en' => $en, 'ar' => $ar], 'kind' => $kind, 'position' => $position]);
    }
    $this->candidate = Candidate::factory()->create(['name' => 'Lina Haddad', 'email' => 'lina@example.com', 'locale' => 'en']);
    $this->application = JobApplication::query()->create([
        'candidate_id' => $this->candidate->id, 'job_requisition_id' => $this->requisition->id,
        'pipeline_stage_id' => $this->requisition->stages()->where('kind', 'offer')->sole()->id,
        'source' => ApplicationSource::Careers, 'applied_at' => now(), 'stage_entered_at' => now(), 'last_activity_at' => now(),
    ]);
});

function hiringOfferDraft(object $test, array $overrides = []): Offer
{
    return resolve(SaveOffer::class)->handle(Actor::user($test->recruiter), $test->application, OfferInputData::from([
        'jobTitle' => 'Senior Barista',
        'startOn' => CarbonImmutable::now()->addMonth()->toDateString(),
        'salary' => ['amount_minor' => 850000, 'currency' => 'SAR'],
        'branchId' => $test->branch->ulid,
        'managerId' => $test->manager->ulid,
        'expiresOn' => CarbonImmutable::now()->addWeeks(2)->toDateString(),
        ...$overrides,
    ]));
}

function hiringOfferDecide(Offer $offer, User $as, Decision $decision = Decision::Approved): void
{
    $task = WorkflowTask::query()->where('workflow_instance_id', $offer->fresh()?->workflow_instance_id)->where('status', TaskStatus::Open->value)->sole();
    expect($task->assignee_user_id)->toBe($as->id);
    resolve(WorkflowEngine::class)->decide($task, $decision, $as, $decision === Decision::Approved ? null : 'Too high');
}

function hiringOfferSent(object $test): Offer
{
    $offer = hiringOfferDraft($test);
    resolve(SubmitOffer::class)->handle(Actor::user($test->recruiter), $offer);
    hiringOfferDecide($offer, $test->managerUser);
    hiringOfferDecide($offer, $test->hr);

    return resolve(SendOffer::class)->handle(Actor::user($test->recruiter), $offer->fresh() ?? $offer, MessageChannel::Email);
}

/** A 1×1 transparent PNG, as the signature pad would send. */
function hiringSignature(): string
{
    return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
}

it('drafts an offer letter from the template in the candidate’s language', function (): void {
    $offer = hiringOfferDraft($this);

    expect($offer->status)->toBe(OfferStatus::Draft)
        ->and($offer->salary)->toEqual(new Money(850000, 'SAR'))
        ->and($offer->letter)->toContain('Dear Lina Haddad')->toContain('Senior Barista')->toContain('Riyadh HQ')
        ->and(fn () => hiringOfferDraft($this))->toThrow(DomainRuleViolation::class);

    $this->candidate->forceFill(['locale' => 'ar'])->save();
    $preview = $this->actingAs($this->recruiter)->postJson(route('hiring.offers.letter', $this->application), [
        'jobTitle' => 'باريستا أول', 'startOn' => '2026-12-01', 'salary' => ['amount_minor' => 850000, 'currency' => 'SAR'], 'branchId' => $this->branch->ulid,
    ])->assertOk()->json('letter');

    expect($preview)->toContain('عزيزي/عزيزتي Lina Haddad')->toContain('المقر الرئيسي');
});

it('runs the offer through the hiring manager and HR, then the candidate signs it', function (): void {
    $offer = hiringOfferDraft($this);
    resolve(SubmitOffer::class)->handle(Actor::user($this->recruiter), $offer);

    // The approver sees what the offer is about in the inbox.
    $task = WorkflowTask::query()->where('workflow_instance_id', $offer->fresh()?->workflow_instance_id)->sole();
    $data = resolve(RequestPresenter::class)->task($task, null, $this->managerUser);
    expect($data->about)->toBe('Offer: Senior Barista')
        ->and(collect($data->facts)->pluck('value'))->toContain('Lina Haddad')
        ->and($data->url)->toBe(route('hiring.offers.pdf', $offer));
    $this->actingAs($this->managerUser)->get(route('hiring.offers.pdf', $offer))->assertOk()->assertHeader('Content-Type', 'application/pdf');

    hiringOfferDecide($offer, $this->managerUser);
    hiringOfferDecide($offer, $this->hr);
    expect($offer->fresh()?->status)->toBe(OfferStatus::Approved);

    $this->actingAs($this->recruiter)->post(route('hiring.offers.send', $offer), ['channel' => 'email'])->assertRedirect();
    expect($offer->fresh()?->status)->toBe(OfferStatus::Sent)
        ->and(CandidateMessage::query()->where('purpose', 'offer')->count())->toBe(1);

    $token = SignedTokens::make(CandidateLinks::OFFER, $offer->ulid);
    $this->get(route('careers.offers.show', $token))->assertInertia(fn (Assert $page): Assert => $page->component('public/offer')->where('offer.status', 'sent')->where('offer.salary', fn (string $salary): bool => str_contains($salary, '8,500.00')));

    $this->post(route('careers.offers.accept', $token), ['name' => 'Lina Haddad', 'signature' => 'data:image/png;base64,'.base64_encode('not a png'), 'agree' => true])->assertSessionHasErrors('signature');
    $this->post(route('careers.offers.accept', $token), ['name' => 'Lina Haddad', 'signature' => hiringSignature()])->assertSessionHasErrors('agree');
    $this->post(route('careers.offers.accept', $token), ['name' => 'Lina Haddad', 'signature' => hiringSignature(), 'agree' => true])->assertRedirect();

    $offer->refresh();
    expect($offer->status)->toBe(OfferStatus::Accepted)
        ->and($offer->signed_name)->toBe('Lina Haddad')
        ->and(Storage::disk('local')->exists((string) $offer->signature_path))->toBeTrue()
        ->and(Storage::disk('local')->get((string) $offer->signed_pdf_path))->toBe('%PDF-1.4 signed letter');

    $this->post(route('careers.offers.decline', $token), ['reason' => 'Changed my mind'])->assertSessionHasErrors('error');
});

it('ends an offer the hiring manager doesn’t approve, and records a declined one', function (): void {
    $offer = hiringOfferDraft($this);
    resolve(SubmitOffer::class)->handle(Actor::user($this->recruiter), $offer);
    hiringOfferDecide($offer, $this->managerUser, Decision::Rejected);

    expect($offer->fresh()?->status)->toBe(OfferStatus::Rejected);

    $second = hiringOfferSent($this);
    $this->post(route('careers.offers.decline', SignedTokens::make(CandidateLinks::OFFER, $second->ulid)), ['reason' => 'Accepted another offer'])->assertRedirect();

    expect($second->fresh())->status->toBe(OfferStatus::Declined)->decline_reason->toBe('Accepted another offer');
});

it('hires the candidate once they accepted, starting onboarding and filling the requisition', function (): void {
    expect(fn () => resolve(HireCandidate::class)->handle(Actor::user($this->hr), $this->application, HireCandidateData::from(['nameEn' => 'Lina Haddad', 'hireDate' => '2026-12-01', 'branchId' => $this->branch->ulid])))->toThrow(DomainRuleViolation::class);

    $offer = hiringOfferSent($this);
    $this->post(route('careers.offers.accept', SignedTokens::make(CandidateLinks::OFFER, $offer->ulid)), ['name' => 'Lina Haddad', 'signature' => hiringSignature(), 'agree' => true]);

    $this->actingAs($this->hr)->get(route('hiring.candidates.show', $this->candidate))
        ->assertInertia(fn (Assert $page): Assert => $page->where('applications.0.hire.hireDate', $offer->start_on->toDateString()));

    $this->post(route('hiring.applications.hire', $this->application), [
        'nameEn' => 'Lina Haddad', 'hireDate' => $offer->start_on->toDateString(), 'branchId' => $this->branch->ulid, 'managerId' => $this->manager->ulid,
    ])->assertRedirect();

    $employee = Employee::query()->where('name_en', 'Lina Haddad')->sole();
    expect($employee->status)->toBe(EmployeeStatus::Pending)
        ->and(EmploymentHistoryEntry::query()->where('employee_id', $employee->id)->whereNotNull('salary_minor')->sole()->salary)->toEqual(new Money(850000, 'SAR'))
        ->and($this->application->fresh())->status->toBe(ApplicationStatus::Hired)->employee_id->toBe($employee->id)
        ->and($this->application->fresh()?->stage()->firstOrFail()->kind->value)->toBe('hired')
        ->and($this->requisition->fresh()?->status)->toBe(RequisitionStatus::Filled)
        ->and(fn () => resolve(AnonymiseCandidate::class)->handle(Actor::system('test'), $this->candidate, 'hr'))->toThrow(DomainRuleViolation::class);
});

it('anonymises a candidate on request and purges old ones past the retention period', function (): void {
    $this->actingAs($this->recruiter)->post(route('hiring.candidates.messages', $this->candidate), ['channel' => 'email', 'subject' => 'Hi', 'body' => 'Hello']);

    $token = SignedTokens::make(CandidateLinks::PRIVACY, $this->candidate->ulid);
    $this->get(route('careers.privacy.show', $token))->assertInertia(fn (Assert $page): Assert => $page->component('public/candidate-privacy')->where('deleted', false));
    $this->post(route('careers.privacy.forget', $token))->assertRedirect();

    $this->candidate->refresh();
    expect($this->candidate)->name->toBe(AnonymiseCandidate::NAME)->email->toBeNull()->phone->toBeNull()->anonymised_at->not->toBeNull()
        ->and($this->application->fresh()?->status)->toBe(ApplicationStatus::Withdrawn)
        ->and(CandidateMessage::query()->count())->toBe(0);

    $old = Candidate::factory()->create(['last_activity_at' => now()->subMonths(13)]);
    $pooled = Candidate::factory()->create(['last_activity_at' => now()->subMonths(13), 'talent_pool' => true, 'talent_pool_consented_at' => now()->subMonths(13)]);
    $active = Candidate::factory()->create(['last_activity_at' => now()->subMonths(13)]);
    JobApplication::query()->create([
        'candidate_id' => $active->id, 'job_requisition_id' => $this->requisition->id, 'pipeline_stage_id' => $this->requisition->stages()->firstOrFail()->id,
        'source' => ApplicationSource::Manual, 'applied_at' => now(), 'stage_entered_at' => now(), 'last_activity_at' => now(),
    ]);

    expect(resolve(PurgeCandidates::class)->handle(CarbonImmutable::now()))->toBe(1)
        ->and($old->fresh()?->anonymised_at)->not->toBeNull()
        ->and($pooled->fresh()?->anonymised_at)->toBeNull()
        ->and($active->fresh()?->anonymised_at)->toBeNull();
    $this->artisan('hiring:purge')->assertSuccessful();
});

it('keeps offers and hiring away from panelists and other branches', function (): void {
    $offer = hiringOfferDraft($this);
    $other = userWithRoles('branch_manager');
    Employee::factory()->placedIn(Branch::factory()->riyadh()->create())->create(['user_id' => $other->id]);

    $this->actingAs($other)->get(route('hiring.offers.pdf', $offer))->assertNotFound();
    $this->actingAs($other)->post(route('hiring.offers.submit', $offer))->assertNotFound();
    $this->actingAs($this->managerUser)->post(route('hiring.offers.submit', $offer))->assertForbidden();
    $this->actingAs($other)->post(route('hiring.applications.hire', $this->application), ['nameEn' => 'x', 'hireDate' => '2026-12-01', 'branchId' => $this->branch->ulid])->assertNotFound();
    $this->get(route('careers.offers.show', SignedTokens::make(CandidateLinks::OFFER, $offer->ulid)))->assertNotFound();
});
