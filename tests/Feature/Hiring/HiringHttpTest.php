<?php

declare(strict_types=1);

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\SolveChallengeOptions;
use App\Domain\Forms\Models\FormUpload;
use App\Domain\Hiring\Actions\ChangePostingStatus;
use App\Domain\Hiring\Actions\SaveJobPosting;
use App\Domain\Hiring\Actions\SeedHiringDefaults;
use App\Domain\Hiring\Data\JobPostingInputData;
use App\Domain\Hiring\Enums\ApplicationStatus;
use App\Domain\Hiring\Enums\PostingStatus;
use App\Domain\Hiring\Enums\RequisitionStatus;
use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Models\PipelineStage;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Actions\SeedRequestTypes;
use App\Support\Actors\Actor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Phase 6a over HTTP: the Hiring screens, /api/v1/hiring, and the public careers site.
*/
beforeEach(function (): void {
    Mail::fake();
    Storage::fake('local');
    config(['services.altcha' => ['cost' => 1, 'counter_min' => 1, 'counter_max' => 20]]);
    seedAccess();
    resolve(SeedRequestTypes::class)->handle();
    resolve(SeedHiringDefaults::class)->handle();

    $this->branch = Branch::factory()->riyadh()->create(['name' => ['en' => 'Riyadh HQ', 'ar' => 'المقر الرئيسي']]);
    $this->otherBranch = Branch::factory()->riyadh()->create();
    $this->recruiter = userWithRoles('recruiter');
    $this->managerUser = userWithRoles('branch_manager');
    Employee::factory()->placedIn($this->branch)->create(['user_id' => $this->managerUser->id]);
    $this->otherManager = userWithRoles('branch_manager');
    Employee::factory()->placedIn($this->otherBranch)->create(['user_id' => $this->otherManager->id]);

    $this->requisition = hiringHttpOpen($this->branch);
    $this->posting = resolve(SaveJobPosting::class)->handle(Actor::user($this->recruiter), $this->requisition, JobPostingInputData::from([
        'title' => ['en' => 'Senior Barista', 'ar' => 'باريستا أول'],
        'description' => ['en' => "Make great coffee.\nLead the morning shift.", 'ar' => 'حضّر قهوة رائعة.'],
        'summary' => ['en' => 'Coffee lovers wanted', 'ar' => 'نبحث عن محبي القهوة'],
    ]));
    resolve(ChangePostingStatus::class)->handle(Actor::user($this->recruiter), $this->posting, PostingStatus::Published);
});

function hiringHttpOpen(Branch $branch): JobRequisition
{
    // An approved requisition with the default pipeline, as the approval's last step leaves it.
    $requisition = JobRequisition::factory()->create(['branch_id' => $branch->id, 'title' => ['en' => 'Senior Barista', 'ar' => 'باريستا أول']]);

    foreach (SeedHiringDefaults::stages() as $position => [$kind, $en, $ar]) {
        PipelineStage::query()->create(['job_requisition_id' => $requisition->id, 'name' => ['en' => $en, 'ar' => $ar], 'kind' => $kind, 'position' => $position]);
    }

    return $requisition;
}

function careersAltcha(object $test, string $slug): string
{
    $challenge = Challenge::fromArray($test->getJson(route('careers.challenge', $slug))->assertOk()->json());
    $solution = (new Altcha(hmacSignatureSecret: 'unused'))->solveChallenge(new SolveChallengeOptions(challenge: $challenge, algorithm: new Pbkdf2));

    return (new Payload($challenge, $solution))->toBase64();
}

function careersApply(object $test, array $overrides = []): TestResponse
{
    $slug = $test->posting->slug;
    // One visitor: uploads only match the session that made them.
    $test->withCookie(config('session.cookie'), str_repeat('v', 40));
    $cv = $test->post(route('careers.uploads', $slug), ['field' => 'cv', 'file' => UploadedFile::fake()->create('cv.pdf', 30, 'application/pdf')])->assertCreated()->json('id');

    return $test->post(route('careers.apply', $slug), [
        'values' => ['full_name' => 'Omar Saleh', 'email' => 'omar@example.com', 'phone' => '+966500000009', 'cv' => [$cv], 'right_to_work' => 'yes'],
        'startedAt' => Crypt::encryptString((string) (time() - 30)),
        'altcha' => careersAltcha($test, $slug),
        'privacy' => true,
        ...$overrides,
    ]);
}

it('lists open jobs on the careers site with search and share tags', function (): void {
    $this->get(route('careers.index', ['lang' => 'ar']))
        ->assertOk()
        ->assertSee('<meta property="og:image" content="'.asset('brand/og-careers-ar.png').'">', false)
        ->assertSee('hreflang="en"', false)
        ->assertInertia(fn (Assert $page): Assert => $page->component('public/careers')->has('jobs', 1)->where('jobs.0.title', 'باريستا أول'));

    $this->get(route('careers.show', [$this->posting->slug, 'lang' => 'en']))
        ->assertOk()
        ->assertSee('application/ld+json', false)
        ->assertSee('"@type":"JobPosting"', false)
        ->assertInertia(fn (Assert $page): Assert => $page->component('public/job')->where('open', true)->has('schema')->where('job.branch', 'Riyadh HQ'));

    $this->get(route('careers.sitemap'))->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->assertSee($this->posting->slug);

    // A draft or a closed requisition's posting is not listed.
    $this->requisition->forceFill(['status' => RequisitionStatus::OnHold])->save();
    $this->get(route('careers.index'))->assertInertia(fn (Assert $page): Assert => $page->has('jobs', 0));
    $this->get(route('careers.show', $this->posting->slug))->assertInertia(fn (Assert $page): Assert => $page->where('open', false)->where('schema', null));
});

it('applies through the careers site with a CV, consent and ALTCHA', function (): void {
    careersApply($this, ['privacy' => false])->assertSessionHasErrors('privacy');
    careersApply($this, ['altcha' => null])->assertSessionHasErrors('altcha');

    careersApply($this, ['talentPool' => true])->assertRedirect(route('careers.show', $this->posting->slug));

    $application = JobApplication::query()->sole();
    expect($application->status)->toBe(ApplicationStatus::Active)
        ->and($application->cv_upload_id)->not->toBeNull()
        ->and($application->candidate()->firstOrFail())->name->toBe('Omar Saleh')->talent_pool->toBeTrue();

    // The honeypot pretends success and stores nothing.
    careersApply($this, ['website' => 'http://spam.example', 'values' => ['full_name' => 'Bot', 'email' => 'bot@example.com']]);
    expect(JobApplication::query()->count())->toBe(1);
});

it('shows the requisitions table scoped to the viewer, with a constant number of queries', function (): void {
    hiringHttpOpen($this->otherBranch);

    $this->actingAs($this->managerUser)->get(route('hiring.requisitions.index'))
        ->assertInertia(fn (Assert $page): Assert => $page->component('hiring/requisitions')->has('requisitions.data', 1)->where('can.create', true));
    $this->actingAs($this->recruiter)->get(route('hiring.requisitions.index'))
        ->assertInertia(fn (Assert $page): Assert => $page->has('requisitions.data', 2)->where('can.create', false));
    $this->actingAs(userWithRoles('employee'))->get(route('hiring.requisitions.index'))->assertForbidden();

    foreach (range(1, 4) as $i) {
        hiringHttpOpen($this->branch);
    }

    $this->actingAs($this->recruiter)->get(route('hiring.requisitions.index'))->assertOk();
    DB::enableQueryLog();
    $this->get(route('hiring.requisitions.index'))->assertOk();
    $few = count(DB::getQueryLog());
    foreach (range(1, 6) as $i) {
        hiringHttpOpen($this->branch);
    }
    DB::flushQueryLog();
    $this->get(route('hiring.requisitions.index'))->assertOk();
    expect(count(DB::getQueryLog()))->toBe($few);
});

it('raises a requisition from the Hiring screen', function (): void {
    $this->actingAs($this->managerUser)->get(route('hiring.requisitions.create'))
        ->assertInertia(fn (Assert $page): Assert => $page->component('hiring/requisition-create')->where('type.code', 'JOB_REQUISITION')->has('schema'));

    $this->post(route('hiring.requisitions.store'), ['values' => [
        'title' => 'Cashier', 'branch' => $this->branch->ulid, 'headcount' => 1, 'reason' => 'new', 'justification' => 'Growth',
    ]])->assertRedirect();

    expect(JobRequisition::query()->where('status', RequisitionStatus::Pending)->sole()->getTranslation('title', 'en'))->toBe('Cashier');
    $this->actingAs($this->recruiter)->get(route('hiring.requisitions.create'))->assertForbidden();
});

it('shows the board and moves candidates, refusing people outside the requisition', function (): void {
    careersApply($this);
    $application = JobApplication::query()->sole();
    $screening = PipelineStage::query()->where('job_requisition_id', $this->requisition->id)->where('kind', 'screening')->sole();

    $this->actingAs($this->recruiter)->get(route('hiring.board', $this->requisition))
        ->assertInertia(fn (Assert $page): Assert => $page->component('hiring/board')->has('stages', 5)->has('cards', 1)->where('cards.0.name', 'Omar Saleh')->where('can.update', true));

    $this->post(route('hiring.applications.move', $application), ['stage' => $screening->ulid])->assertRedirect();
    expect($application->fresh()?->pipeline_stage_id)->toBe($screening->id);

    // Another branch's manager can't see it at all; this branch's manager sees but can't act.
    $this->actingAs($this->otherManager)->get(route('hiring.board', $this->requisition))->assertNotFound();
    $this->actingAs($this->otherManager)->postJson(route('api.v1.hiring.applications.move', $application), ['stage' => $screening->ulid])->assertNotFound();
    $this->actingAs($this->managerUser)->get(route('hiring.board', $this->requisition))->assertOk();
    $this->actingAs($this->managerUser)->postJson(route('api.v1.hiring.applications.move', $application), ['stage' => $screening->ulid])->assertForbidden();

    $this->actingAs($this->recruiter)->post(route('hiring.applications.reject'), ['applications' => [$application->ulid], 'reason' => 'not_qualified'])->assertRedirect();
    expect($application->fresh()?->status)->toBe(ApplicationStatus::Rejected);
});

it('opens a candidate profile with the CV, scoped like the board', function (): void {
    careersApply($this);
    $application = JobApplication::query()->sole();
    $candidate = Candidate::query()->sole();

    $this->actingAs($this->recruiter)->get(route('hiring.candidates.show', $candidate))
        ->assertInertia(fn (Assert $page): Assert => $page->component('hiring/candidate')->where('candidate.email', 'omar@example.com')->has('applications', 1)->has('applications.0.cv.url'));
    $cv = FormUpload::query()->findOrFail($application->cv_upload_id);
    $this->get(route('hiring.applications.files', [$application, $cv]))->assertOk()->assertHeader('Content-Type', 'application/pdf');

    $this->put(route('hiring.candidates.tags', $candidate), ['tags' => ['Barista', 'Arabic']])->assertRedirect();
    expect($candidate->tags()->pluck('name')->sort()->values()->all())->toBe(['Arabic', 'Barista']);
    $this->get(route('hiring.candidates.index', ['tag' => 'barista']))->assertInertia(fn (Assert $page): Assert => $page->has('candidates.data', 1));

    $this->actingAs($this->otherManager)->get(route('hiring.candidates.show', $candidate))->assertNotFound();
    $this->actingAs($this->otherManager)->get(route('hiring.applications.files', [$application, $cv]))->assertNotFound();
    $this->actingAs($this->otherManager)->getJson(route('api.v1.hiring.candidates'))->assertOk()->assertJsonCount(0, 'data');
});

it('edits and publishes a posting, and keeps settings for hiring admins', function (): void {
    $this->actingAs($this->recruiter)->get(route('hiring.postings.edit', $this->posting))
        ->assertInertia(fn (Assert $page): Assert => $page->component('hiring/posting')->where('posting.title.ar', 'باريستا أول')->has('forms', 1));

    $this->put(route('hiring.postings.update', $this->posting), [
        'title' => ['en' => 'Head Barista', 'ar' => ''], 'description' => ['en' => 'Coffee.'], 'workplace' => 'hybrid', 'showSalary' => true,
        'knockouts' => [['field' => 'location', 'rule' => ['op' => 'empty', 'args' => [['ref' => 'field.location']]]]],
    ])->assertSessionHasNoErrors();

    expect($this->posting->fresh())->getTranslation('title', 'ar')->toBe('Head Barista')->knockouts->toHaveCount(1);

    $this->put(route('hiring.postings.status', $this->posting), ['status' => 'closed'])->assertRedirect();
    expect($this->posting->fresh()?->status)->toBe(PostingStatus::Closed);

    $this->get(route('hiring.settings'))->assertOk();
    $this->put(route('hiring.settings.update'), ['careersIntro' => ['en' => 'Work with us'], 'privacyNotice' => ['en' => 'We keep it safe']])->assertRedirect();
    $this->actingAs($this->managerUser)->get(route('hiring.settings'))->assertForbidden();
    $this->actingAs($this->managerUser)->get(route('hiring.postings.edit', $this->posting))->assertForbidden();
    $this->actingAs($this->otherManager)->get(route('hiring.postings.edit', $this->posting))->assertNotFound();
});

it('lists requisitions and hiring team people over the API', function (): void {
    $this->actingAs($this->otherManager)->getJson(route('api.v1.hiring.requisitions'))->assertOk()->assertJsonCount(0, 'data');
    $this->actingAs($this->recruiter)->getJson(route('api.v1.hiring.requisitions', ['status' => 'open']))->assertOk()->assertJsonPath('data.0.id', $this->requisition->ulid);
    $this->getJson(route('api.v1.hiring.people', ['search' => $this->recruiter->name]))->assertOk()->assertJsonPath('data.0.id', $this->recruiter->ulid);
    $this->actingAs($this->managerUser)->getJson(route('api.v1.hiring.people'))->assertForbidden();
});
