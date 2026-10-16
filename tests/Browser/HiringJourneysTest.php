<?php

declare(strict_types=1);

use App\Domain\Forms\Actions\StoreFormUpload;
use App\Domain\Forms\Models\FormVersion;
use App\Domain\Hiring\Actions\ChangePostingStatus;
use App\Domain\Hiring\Actions\SaveJobPosting;
use App\Domain\Hiring\Actions\SeedHiringDefaults;
use App\Domain\Hiring\Actions\SubmitApplication;
use App\Domain\Hiring\Data\JobPostingInputData;
use App\Domain\Hiring\Enums\ApplicationSource;
use App\Domain\Hiring\Enums\PostingStatus;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Models\PipelineStage;
use App\Domain\Hiring\Support\HiringSettings;
use App\Domain\Organization\Models\Branch;
use App\Domain\Requests\Actions\SeedRequestTypes;
use App\Support\Actors\Actor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

/*
| Phase 6a journeys (EN + AR, axe): move a candidate on the board (the Phase 6 exit journey),
| apply on the careers site, and the hiring screens.
*/
beforeEach(function (): void {
    config(['services.altcha' => ['cost' => 1, 'counter_min' => 1, 'counter_max' => 20]]);
    seedAccess();
    resolve(SeedRequestTypes::class)->handle();
    resolve(SeedHiringDefaults::class)->handle();
    $branch = Branch::factory()->riyadh()->create(['name' => ['en' => 'Riyadh HQ', 'ar' => 'المقر الرئيسي']]);

    $this->recruiter = userWithRoles('recruiter');
    $this->recruiter->forceFill(['name' => 'Reem Recruiter'])->save();
    $this->requisition = JobRequisition::factory()->create(['branch_id' => $branch->id, 'title' => ['en' => 'Barista', 'ar' => 'باريستا']]);

    foreach (SeedHiringDefaults::stages() as $position => [$kind, $en, $ar]) {
        PipelineStage::query()->create(['job_requisition_id' => $this->requisition->id, 'name' => ['en' => $en, 'ar' => $ar], 'kind' => $kind, 'position' => $position]);
    }

    $posting = resolve(SaveJobPosting::class)->handle(Actor::user($this->recruiter), $this->requisition, JobPostingInputData::from([
        'title' => ['en' => 'Barista', 'ar' => 'باريستا'],
        'description' => ['en' => "Make great coffee.\n\n- Two years of experience\n- Morning shifts", 'ar' => "حضّر قهوة رائعة.\n\n- خبرة سنتين\n- مناوبات صباحية"],
        'summary' => ['en' => 'Full time in Riyadh', 'ar' => 'دوام كامل في الرياض'],
    ]));
    $this->posting = resolve(ChangePostingStatus::class)->handle(Actor::user($this->recruiter), $posting, PostingStatus::Published);
});

function hiringBrowserApplicant(object $test, string $name, string $email): JobApplication
{
    $form = resolve(HiringSettings::class)->defaultForm();
    $version = FormVersion::query()->findOrFail($form?->published_version_id);
    $cv = resolve(StoreFormUpload::class)->handle($form, $version, 'cv', UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf'), null, 'visitor')->ulid;

    return resolve(SubmitApplication::class)->handle(Actor::system('careers'), $test->requisition, $test->posting, [
        'full_name' => $name, 'email' => $email, 'phone' => '+966500000077', 'right_to_work' => 'yes', 'cv' => [$cv],
    ], ApplicationSource::Careers, ['privacy' => true, 'talentPool' => false], 'visitor');
}

it('moves a candidate to the next stage from the board', function (string $locale): void {
    $application = hiringBrowserApplicant($this, 'Lina Haddad', 'lina@example.com');
    $this->recruiter->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->recruiter);

    visit("/hiring/requisitions/{$this->requisition->ulid}")
        ->on()->macbook14()
        ->wait(1)
        ->assertPresent("[data-card=\"{$application->ulid}\"]")
        ->click("[data-card=\"{$application->ulid}\"] [data-card-menu]")
        ->wait(0.4)
        ->click('[data-move-to="screening"]')
        ->wait(1.2)
        ->assertPresent("[data-stage-kind=\"screening\"] [data-card=\"{$application->ulid}\"]")
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    expect($application->fresh()?->stage()->firstOrFail()->kind->value)->toBe('screening');
})->with(['en', 'ar']);

it('applies for a job on the careers site', function (string $locale): void {
    File::ensureDirectoryExists(storage_path('app/tmp'));
    $cv = storage_path("app/tmp/browser-cv-{$locale}.pdf");
    File::put($cv, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
    $page = visit("/careers/{$this->posting->slug}?lang={$locale}")->wait(1);

    $page->assertNoAccessibilityIssues()
        ->fill('#field-full_name', 'Omar Saleh')
        ->fill('#field-email', 'omar@example.com')
        ->fill('#field-phone', '+966500000099')
        ->attach('#field-cv', $cv)
        ->wait(1.5)
        ->click($locale === 'ar' ? 'نعم' : 'Yes')
        ->click('[data-privacy]')
        ->wait(3)
        ->click($locale === 'ar' ? 'إرسال الطلب' : 'Send application')
        ->wait(1.5)
        ->assertPresent('[data-applied]')
        ->assertNoJavaScriptErrors();

    expect(JobApplication::query()->sole()->candidate()->firstOrFail()->name)->toBe('Omar Saleh');
})->with(['en', 'ar']);

it('renders the hiring screens accessibly in both languages', function (string $locale): void {
    $application = hiringBrowserApplicant($this, 'Lina Haddad', 'lina@example.com');
    $this->recruiter->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->recruiter);
    $candidate = $application->candidate()->firstOrFail();

    foreach ([
        '/hiring/requisitions?status=all&open='.$this->requisition->ulid,
        "/hiring/requisitions/{$this->requisition->ulid}",
        "/hiring/postings/{$this->posting->ulid}",
        '/hiring/candidates',
        "/hiring/candidates/{$candidate->ulid}",
        '/hiring/settings',
    ] as $path) {
        visit($path)->on()->macbook14()->wait(1)->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    }

    foreach (["/hiring/requisitions/{$this->requisition->ulid}", "/hiring/candidates/{$candidate->ulid}", "/careers?lang={$locale}"] as $path) {
        visit($path)->on()->mobile()->wait(1)
            ->assertScript('document.documentElement.scrollWidth <= document.documentElement.clientWidth', true)
            ->assertNoJavaScriptErrors()
            ->assertNoAccessibilityIssues();
    }
})->with(['en', 'ar']);

/*
 * Visual check (handoff 28–31, D32): 390 / 834 / 1440 × LTR / RTL; the board ★ also in dark.
 */
it('captures the hiring screens for review', function (string $locale): void {
    $application = hiringBrowserApplicant($this, 'Lina Haddad', 'lina@example.com');
    hiringBrowserApplicant($this, 'Sam Carter', 'sam@example.com');
    $this->recruiter->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->recruiter);

    $shots = [
        'requisitions' => '/hiring/requisitions?status=all&open='.$this->requisition->ulid,
        'board' => "/hiring/requisitions/{$this->requisition->ulid}",
        'candidate' => "/hiring/candidates/{$application->candidate()->firstOrFail()->ulid}",
        'careers' => "/careers?lang={$locale}",
        'job' => "/careers/{$this->posting->slug}?lang={$locale}",
    ];

    foreach ($shots as $name => $path) {
        visit($path)->on()->mobile()->wait(1)->screenshot(true, "hiring-{$name}-{$locale}-390");
        visit($path)->on()->iPadPro()->wait(1)->screenshot(true, "hiring-{$name}-{$locale}-834");
        visit($path)->on()->macbook14()->wait(1)->screenshot(true, "hiring-{$name}-{$locale}-1440");
    }

    visit($shots['board'])->on()->macbook14()->inDarkMode()->wait(1)->screenshot(true, "hiring-board-{$locale}-dark");
})->with(['en', 'ar'])->skip(fn (): bool => ! env('SCREENSHOTS'), 'Set SCREENSHOTS=1 to capture.');
