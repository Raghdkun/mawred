<?php

declare(strict_types=1);

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
use App\Domain\Training\Enums\CourseStatus;
use App\Domain\Training\Enums\EnrollmentSource;
use App\Domain\Training\Enums\EnrollmentStatus;
use App\Domain\Training\Models\Certificate;
use App\Domain\Training\Models\Enrollment;
use App\Domain\Training\Models\ExternalTraining;
use App\Domain\Training\Models\TrainingSession;
use App\Domain\Training\Support\CheckInCodes;
use App\Support\Actors\Actor;
use Carbon\CarbonImmutable;

/*
| Phase 7b journeys (EN + AR, axe): book a seat and check in from the trainer's QR, plan a
| session, the public certificate check, and the records on My learning and the profile.
*/
beforeEach(function (): void {
    seedAccess();
    resolve(SeedTrainingDefaults::class)->handle();
    $branch = Branch::factory()->riyadh()->create(['name' => ['en' => 'Riyadh HQ', 'ar' => 'المقر الرئيسي']]);
    $this->trainer = userWithRoles('trainer');
    $this->trainer->forceFill(['name' => 'Tala Trainer'])->save();
    $this->learnerUser = userWithRoles('employee');
    $this->learner = Employee::factory()->placedIn($branch)->create(['user_id' => $this->learnerUser->id, 'name_en' => 'Lina Learner', 'name_ar' => 'لينا المتعلمة']);

    $course = resolve(SaveCourse::class)->handle(Actor::user($this->trainer), CourseInputData::from([
        'title' => ['en' => 'Barista class', 'ar' => 'دورة الباريستا'], 'category' => 'skills', 'validityMonths' => 12,
    ]));
    resolve(SaveCourseModules::class)->handle(Actor::user($this->trainer), $course, ModulesInputData::from(['modules' => [
        ['kind' => 'text', 'title' => ['en' => 'Before class', 'ar' => 'قبل الدورة'], 'body' => ['en' => 'Bring an apron.', 'ar' => 'أحضر مريلة.']],
    ]]));
    $this->course = resolve(ChangeCourseStatus::class)->handle(Actor::user($this->trainer), $course->fresh() ?? $course, CourseStatus::Published);
    $starts = CarbonImmutable::now()->addMinutes(20)->startOfMinute();
    $this->session = resolve(SaveSession::class)->handle(Actor::user($this->trainer), $this->course, SessionInputData::from([
        'startsAt' => $starts->toIso8601String(), 'endsAt' => $starts->addHours(2)->toIso8601String(), 'timezone' => 'Asia/Riyadh',
        'location' => 'Training room 2', 'capacity' => 8, 'trainerId' => $this->trainer->ulid,
    ]));
    $this->enrollment = resolve(Enrol::class)->handle(Actor::user($this->trainer), $this->learner, $this->course, EnrollmentSource::Assigned);
});

it('books a seat, checks in from the QR and gets the certificate', function (string $locale): void {
    $this->learnerUser->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->learnerUser);

    visit("/training/courses/{$this->course->ulid}")->on()->mobile()->wait(1)
        ->assertNoAccessibilityIssues()
        ->click('[data-book-session]')->wait(1.2)
        ->assertPresent('[data-cancel-seat]')
        ->click('[data-continue]')->wait(1)
        ->click('[data-mark-done]')->wait(1.2)
        ->assertNoJavaScriptErrors();

    $code = CheckInCodes::code(TrainingSession::query()->sole(), now()->getTimestamp());
    visit('/training/check-in?code='.rawurlencode($code))->on()->mobile()->wait(1)
        ->assertNoAccessibilityIssues()
        ->click('[data-confirm-check-in]')->wait(1.5)
        ->assertPresent('[data-course-certificate]')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    expect(Enrollment::query()->sole()->status)->toBe(EnrollmentStatus::Completed)
        ->and(Certificate::query()->count())->toBe(1);
})->with(['en', 'ar']);

it('lets a trainer plan a session and run check-in', function (string $locale): void {
    $this->trainer->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->trainer);

    visit("/training/manage/courses/{$this->course->ulid}?tab=sessions")->on()->macbook14()->wait(1)
        ->assertNoAccessibilityIssues()
        ->click('[data-new-session]')->wait(0.6)
        ->fill('#session-date', now('Asia/Riyadh')->addDays(7)->toDateString())
        ->fill('#session-location', 'Training room 3')
        ->fill('#session-capacity', '12')
        ->click('[data-save-session]')->wait(1.5)
        ->assertNoJavaScriptErrors();

    expect(TrainingSession::query()->count())->toBe(2);

    visit("/training/manage/sessions/{$this->session->ulid}")->on()->macbook14()->wait(1)->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    visit("/training/manage/sessions/{$this->session->ulid}/check-in")->on()->macbook14()->wait(1.5)
        ->assertPresent('[data-check-in-display] svg')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->with(['en', 'ar']);

it('shows the public certificate check and the training records', function (string $locale): void {
    $this->enrollment->forceFill(['status' => EnrollmentStatus::Completed, 'progress' => 100, 'completed_at' => now()])->save();
    $certificate = Certificate::query()->create([
        'serial' => 'MW-2026-ABCDEFGH', 'employee_id' => $this->learner->id, 'course_id' => $this->course->id, 'enrollment_id' => $this->enrollment->id,
        'title' => $this->course->getTranslations('title'), 'holder' => ['en' => 'Lina Learner', 'ar' => 'لينا المتعلمة'],
        'issued_on' => now()->toDateString(), 'expires_on' => now()->addYear()->toDateString(),
    ]);
    ExternalTraining::query()->create(['employee_id' => $this->learner->id, 'title' => 'Latte art masterclass', 'provider' => 'Coffee Academy', 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-03']);
    $this->learnerUser->forceFill(['locale' => $locale])->save();
    $this->trainer->forceFill(['locale' => $locale])->save();

    visit("/verify/{$certificate->serial}?lang={$locale}")->on()->mobile()->wait(1)
        ->assertPresent('[data-certificate-state="valid"]')
        ->assertScript('document.documentElement.scrollWidth <= document.documentElement.clientWidth', true)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    $this->actingAs($this->learnerUser);
    visit('/training/my')->on()->macbook14()->wait(1)->assertPresent('[data-certificates]')->assertPresent('[data-external]')->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    visit("/training/courses/{$this->course->ulid}")->on()->macbook14()->wait(1)->assertPresent('[data-feedback]')->assertNoAccessibilityIssues();

    $this->actingAs($this->trainer);
    visit("/people/{$this->learner->ulid}?tab=training")->on()->macbook14()->wait(1.5)->assertPresent('[data-certificates]')->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    visit("/training/manage/courses/{$this->course->ulid}/people")->on()->macbook14()->wait(1)->assertNoAccessibilityIssues();
})->with(['en', 'ar']);

/*
 * Visual check (handoff 34–35, D32): 390 / 834 / 1440 × LTR / RTL; the verification page also in dark.
 */
it('captures the session and certificate screens for review', function (string $locale): void {
    $this->enrollment->forceFill(['status' => EnrollmentStatus::Completed, 'progress' => 100, 'completed_at' => now()])->save();
    $certificate = Certificate::query()->create([
        'serial' => 'MW-2026-ABCDEFGH', 'employee_id' => $this->learner->id, 'course_id' => $this->course->id, 'enrollment_id' => $this->enrollment->id,
        'title' => $this->course->getTranslations('title'), 'holder' => ['en' => 'Lina Learner', 'ar' => 'لينا المتعلمة'],
        'issued_on' => now()->toDateString(), 'expires_on' => now()->addYear()->toDateString(),
    ]);
    $this->learnerUser->forceFill(['locale' => $locale])->save();
    $this->trainer->forceFill(['locale' => $locale])->save();

    $shots = [
        'course' => [$this->learnerUser, "/training/courses/{$this->course->ulid}"],
        'verify' => [null, "/verify/{$certificate->serial}?lang={$locale}"],
        'roster' => [$this->trainer, "/training/manage/sessions/{$this->session->ulid}"],
        'display' => [$this->trainer, "/training/manage/sessions/{$this->session->ulid}/check-in"],
    ];

    foreach ($shots as $name => [$user, $path]) {
        if ($user !== null) {
            $this->actingAs($user);
        }

        visit($path)->on()->mobile()->wait(1.2)->screenshot(true, "training7b-{$name}-{$locale}-390");
        visit($path)->on()->iPadPro()->wait(1.2)->screenshot(true, "training7b-{$name}-{$locale}-834");
        visit($path)->on()->macbook14()->wait(1.2)->screenshot(true, "training7b-{$name}-{$locale}-1440");
    }

    visit("/verify/{$certificate->serial}?lang={$locale}")->on()->mobile()->inDarkMode()->wait(1)->screenshot(true, "training7b-verify-{$locale}-dark");
})->with(['en', 'ar'])->skip(fn (): bool => ! env('SCREENSHOTS'), 'Set SCREENSHOTS=1 to capture.');
