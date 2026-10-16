<?php

declare(strict_types=1);

use App\Domain\Hiring\Actions\ChangePostingStatus;
use App\Domain\Hiring\Actions\SaveJobPosting;
use App\Domain\Hiring\Actions\SeedHiringDefaults;
use App\Domain\Hiring\Data\JobPostingInputData;
use App\Domain\Hiring\Enums\ApplicationSource;
use App\Domain\Hiring\Enums\PostingStatus;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\JobPosting;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Models\PipelineStage;
use App\Domain\Integrations\Actions\SaveCognitoSource;
use App\Domain\Integrations\Data\CognitoSourceData;
use App\Domain\Integrations\Enums\ReceiptStatus;
use App\Domain\Integrations\Models\CognitoReceipt;
use App\Domain\Organization\Models\Branch;
use App\Domain\Requests\Actions\SeedRequestTypes;
use App\Domain\Requests\Models\RequestType;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Phase 6b: postings that take applications through a mapped Cognito form (D81, D97).
*/
beforeEach(function (): void {
    Mail::fake();
    Storage::fake('local');
    Http::fake(['files.cognitoforms.com/*' => Http::response('%PDF-1.4 cv', 200, ['Content-Type' => 'application/pdf'])]);
    seedAccess();
    resolve(SeedRequestTypes::class)->handle();
    resolve(SeedHiringDefaults::class)->handle();

    $this->recruiter = userWithRoles('recruiter');
    $this->requisition = JobRequisition::factory()->create(['branch_id' => Branch::factory()->riyadh()->create()->id]);
    foreach (SeedHiringDefaults::stages() as $position => [$kind, $en, $ar]) {
        PipelineStage::query()->create(['job_requisition_id' => $this->requisition->id, 'name' => ['en' => $en, 'ar' => $ar], 'kind' => $kind, 'position' => $position]);
    }

    [$this->source, $this->token] = resolve(SaveCognitoSource::class)->handle(Actor::system('test'), CognitoSourceData::from([
        'name' => 'Barista applications', 'requestTypeId' => null, 'formUrl' => 'https://www.cognitoforms.com/Najd/Barista',
        'fields' => [['from' => 'Name', 'to' => 'full_name'], ['from' => 'Email', 'to' => 'email'], ['from' => 'Phone', 'to' => 'phone'], ['from' => 'RightToWork', 'to' => 'right_to_work'], ['from' => 'Resume', 'to' => 'cv']],
        'requesterBy' => 'email', 'requesterPath' => null, 'entryPath' => null, 'versionPath' => null, 'prefill' => [], 'isActive' => true,
    ]));

    $this->postingInput = fn (?string $source): JobPostingInputData => JobPostingInputData::from([
        'title' => ['en' => 'Barista', 'ar' => 'باريستا'], 'description' => ['en' => 'Make great coffee.'], 'cognitoSourceId' => $source,
    ]);
    $posting = resolve(SaveJobPosting::class)->handle(Actor::user($this->recruiter), $this->requisition, ($this->postingInput)($this->source->ulid));
    $this->posting = resolve(ChangePostingStatus::class)->handle(Actor::user($this->recruiter), $posting, PostingStatus::Published);
    $this->payload = [
        'Id' => '9-7', 'Name' => 'Omar Saleh', 'Email' => 'omar@example.com', 'Phone' => '+966500000099', 'RightToWork' => 'yes',
        'Resume' => [['Name' => 'cv.pdf', 'ContentType' => 'application/pdf', 'Size' => 1200, 'File' => 'https://files.cognitoforms.com/najd/cv-7.pdf?token=abc']],
    ];
});

it('turns an entry on the posting’s Cognito form into an application, CV included', function (): void {
    $this->postJson("/hooks/cognito/{$this->token}/submit", $this->payload)->assertSuccessful();

    $application = JobApplication::query()->sole();
    expect($application)->source->toBe(ApplicationSource::Cognito)->job_posting_id->toBe($this->posting->id)->cv_upload_id->not->toBeNull()
        ->and($application->candidate()->firstOrFail())->name->toBe('Omar Saleh')->email->toBe('omar@example.com')
        ->and(CognitoReceipt::query()->sole())->status->toBe(ReceiptStatus::Processed)->job_application_id->toBe($application->id);

    // The careers page sends candidates to the hosted form, and its own form is closed.
    $this->get(route('careers.show', $this->posting->slug))
        ->assertInertia(fn (Assert $page): Assert => $page->component('public/job')->where('open', true)->where('applyUrl', 'https://www.cognitoforms.com/Najd/Barista')->where('schema', null));
    $this->post(route('careers.apply', $this->posting->slug), ['values' => [], 'startedAt' => 'x'])->assertNotFound();

    // The integration screen shows what the source feeds and maps onto the posting's form.
    $admin = userWithRoles('hr_admin');
    $admin->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
    $this->actingAs($admin)->get(route('integrations.cognito.show', $this->source))
        ->assertInertia(fn (Assert $page): Assert => $page->where('source.posting', 'Barista')->where('formFields', fn ($fields): bool => collect($fields)->pluck('key')->contains('cv')));
});

it('fails the entry once the posting closes, and keeps a source to one posting', function (): void {
    resolve(ChangePostingStatus::class)->handle(Actor::user($this->recruiter), $this->posting, PostingStatus::Closed);
    $this->postJson("/hooks/cognito/{$this->token}/submit", $this->payload)->assertSuccessful();

    expect(CognitoReceipt::query()->sole())->status->toBe(ReceiptStatus::Failed)->error->toBe('This job is no longer taking applications.')
        ->and(JobApplication::query()->count())->toBe(0);

    $other = JobRequisition::factory()->create(['branch_id' => $this->requisition->branch_id]);
    expect(fn () => resolve(SaveJobPosting::class)->handle(Actor::user($this->recruiter), $other, ($this->postingInput)($this->source->ulid)))->toThrow(DomainRuleViolation::class)
        ->and(fn () => resolve(SaveCognitoSource::class)->handle(Actor::system('test'), CognitoSourceData::from([
            'name' => 'Barista applications', 'requestTypeId' => RequestType::query()->where('code', 'EXPENSE')->sole()->ulid, 'formUrl' => null, 'fields' => [],
            'requesterBy' => 'email', 'requesterPath' => 'Email', 'entryPath' => null, 'versionPath' => null, 'prefill' => [], 'isActive' => true,
        ]), $this->source))->toThrow(DomainRuleViolation::class);

    // Unlinking hands the careers form back.
    resolve(SaveJobPosting::class)->handle(Actor::user($this->recruiter), $this->requisition, ($this->postingInput)(null), $this->posting);
    expect(JobPosting::query()->find($this->posting->id)?->cognito_source_id)->toBeNull();
});
