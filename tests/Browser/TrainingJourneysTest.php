<?php

declare(strict_types=1);

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\People\Models\Employee;
use App\Domain\Training\Actions\ChangeCourseStatus;
use App\Domain\Training\Actions\ChangeLearningPathStatus;
use App\Domain\Training\Actions\Enrol;
use App\Domain\Training\Actions\SaveCourse;
use App\Domain\Training\Actions\SaveCourseModules;
use App\Domain\Training\Actions\SaveLearningPath;
use App\Domain\Training\Actions\SeedTrainingDefaults;
use App\Domain\Training\Data\CourseInputData;
use App\Domain\Training\Data\LearningPathInputData;
use App\Domain\Training\Data\ModulesInputData;
use App\Domain\Training\Enums\CourseStatus;
use App\Domain\Training\Enums\EnrollmentSource;
use App\Domain\Training\Enums\EnrollmentStatus;
use App\Domain\Training\Models\Course;
use App\Domain\Training\Models\Enrollment;
use App\Support\Actors\Actor;

/*
| Phase 7a journeys (EN + AR, axe): a learner enrols, works through a lesson and passes the
| quiz; a trainer builds and publishes a course; the training screens.
*/
beforeEach(function (): void {
    seedAccess();
    resolve(SeedTrainingDefaults::class)->handle();
    $branch = Branch::factory()->riyadh()->create(['name' => ['en' => 'Riyadh HQ', 'ar' => 'المقر الرئيسي']]);
    $department = Department::factory()->create();

    $this->trainer = userWithRoles('trainer');
    $this->trainer->forceFill(['name' => 'Tala Trainer'])->save();
    $this->learnerUser = userWithRoles('employee');
    $this->learner = Employee::factory()->placedIn($branch, $department)->create(['user_id' => $this->learnerUser->id, 'name_en' => 'Lina Learner']);

    $this->course = resolve(SaveCourse::class)->handle(Actor::user($this->trainer), CourseInputData::from([
        'title' => ['en' => 'Food safety basics', 'ar' => 'أساسيات سلامة الغذاء'],
        'summary' => ['en' => 'Keep food and guests safe.', 'ar' => 'حافظ على سلامة الطعام والضيوف.'],
        'category' => 'safety', 'durationMinutes' => 25, 'validityMonths' => 12,
    ]));
    resolve(SaveCourseModules::class)->handle(Actor::user($this->trainer), $this->course, ModulesInputData::from(['modules' => [
        ['kind' => 'text', 'title' => ['en' => 'Clean hands', 'ar' => 'نظافة اليدين'], 'body' => ['en' => "Wash for 20 seconds.\n\n- Before handling food\n- After breaks", 'ar' => "اغسل يديك 20 ثانية.\n\n- قبل لمس الطعام\n- بعد الاستراحة"]],
        ['kind' => 'quiz', 'title' => ['en' => 'Quick check', 'ar' => 'اختبار سريع'], 'questions' => [
            ['type' => 'single', 'prompt' => ['en' => 'How long should you wash your hands?', 'ar' => 'كم ثانية تغسل يديك؟'], 'options' => [
                ['id' => 'five', 'label' => ['en' => '5 seconds', 'ar' => '5 ثوانٍ'], 'correct' => false],
                ['id' => 'twenty', 'label' => ['en' => '20 seconds', 'ar' => '20 ثانية'], 'correct' => true],
            ]],
        ]],
    ]]));
    $this->course = resolve(ChangeCourseStatus::class)->handle(Actor::user($this->trainer), $this->course->fresh() ?? $this->course, CourseStatus::Published);
});

it('enrols, works through a lesson and passes the quiz', function (string $locale): void {
    $this->learnerUser->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->learnerUser);

    $page = visit("/training/courses/{$this->course->ulid}")->on()->mobile()->wait(1)->assertNoAccessibilityIssues();
    $page->click('[data-enrol]')->wait(1)->click('[data-continue]')->wait(1)
        ->assertPresent('[data-module]')
        ->click('[data-mark-done]')->wait(1)
        ->assertPresent('[data-quiz]')
        ->assertNoAccessibilityIssues()
        ->click('[data-option="twenty"]')
        ->click('[data-submit-quiz]')->wait(1.2)
        ->assertPresent('[data-quiz-result="passed"]')
        ->click('[data-quiz-continue]')->wait(1)
        ->assertPresent('[data-course-completed]')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    expect(Enrollment::query()->sole())->status->toBe(EnrollmentStatus::Completed)->progress->toBe(100);
})->with(['en', 'ar']);

it('lets a trainer build and publish a course', function (string $locale): void {
    $this->trainer->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->trainer);

    visit('/training/manage/courses/new')->on()->macbook14()->wait(1)
        ->assertNoAccessibilityIssues()
        ->fill('#title-en', 'Allergen awareness')
        ->click('[data-save-course]')->wait(1.5)
        ->assertPresent('[data-modules-editor]')
        ->click('[data-add-module="text"]')
        ->fill('[data-module-draft="text"] input[id$="-title-en"]', 'The big 14')
        ->click('[data-save-modules]')->wait(1.5)
        ->click('[data-publish]')->wait(1.5)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    $course = Course::query()->where('title->en', 'Allergen awareness')->sole();
    expect($course->status)->toBe(CourseStatus::Published)->and($course->modules()->count())->toBe(1);
})->with(['en', 'ar']);

it('renders the training screens accessibly in both languages', function (string $locale): void {
    $path = resolve(SaveLearningPath::class)->handle(Actor::user($this->trainer), LearningPathInputData::from(['title' => ['en' => 'Kitchen starter', 'ar' => 'بداية المطبخ'], 'courses' => [$this->course->ulid, $this->course->ulid]]));
    resolve(ChangeLearningPathStatus::class)->handle(Actor::user($this->trainer), $path, CourseStatus::Published);
    resolve(Enrol::class)->handle(Actor::user($this->trainer), $this->learner, $this->course, EnrollmentSource::Assigned, now()->addDays(10)->toDateString());

    foreach ([$this->learnerUser, $this->trainer] as $user) {
        $user->forceFill(['locale' => $locale])->save();
    }

    $this->actingAs($this->learnerUser);
    foreach (['/training', '/training?view=paths', "/training/courses/{$this->course->ulid}", "/training/paths/{$path->ulid}", '/training/my'] as $url) {
        visit($url)->on()->macbook14()->wait(1)->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
        visit($url)->on()->mobile()->wait(1)->assertScript('document.documentElement.scrollWidth <= document.documentElement.clientWidth', true)->assertNoAccessibilityIssues();
    }

    $this->actingAs($this->trainer);
    foreach (['/training/manage/courses', "/training/manage/courses/{$this->course->ulid}?tab=modules", "/training/manage/courses/{$this->course->ulid}?tab=audience", "/training/manage/courses/{$this->course->ulid}/people", '/training/manage/paths', "/people/{$this->learner->ulid}?tab=training"] as $url) {
        visit($url)->on()->macbook14()->wait(1)->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    }
})->with(['en', 'ar']);

/*
 * Visual check (handoff 33–34, D32): 390 / 834 / 1440 × LTR / RTL; the catalog also in dark.
 */
it('captures the training screens for review', function (string $locale): void {
    resolve(Enrol::class)->handle(Actor::user($this->trainer), $this->learner, $this->course, EnrollmentSource::Assigned, now()->addDays(10)->toDateString());
    $quiz = $this->course->modules()->where('kind', 'quiz')->sole();
    $this->learnerUser->forceFill(['locale' => $locale])->save();
    $this->trainer->forceFill(['locale' => $locale])->save();

    $shots = [
        'catalog' => [$this->learnerUser, '/training'],
        'course' => [$this->learnerUser, "/training/courses/{$this->course->ulid}"],
        'quiz' => [$this->learnerUser, "/training/courses/{$this->course->ulid}/learn/{$quiz->ulid}"],
        'my' => [$this->learnerUser, '/training/my'],
        'editor' => [$this->trainer, "/training/manage/courses/{$this->course->ulid}?tab=modules"],
    ];

    foreach ($shots as $name => [$user, $path]) {
        $this->actingAs($user);
        visit($path)->on()->mobile()->wait(1)->screenshot(true, "training-{$name}-{$locale}-390");
        visit($path)->on()->iPadPro()->wait(1)->screenshot(true, "training-{$name}-{$locale}-834");
        visit($path)->on()->macbook14()->wait(1)->screenshot(true, "training-{$name}-{$locale}-1440");
    }

    $this->actingAs($this->learnerUser);
    visit('/training')->on()->macbook14()->inDarkMode()->wait(1)->screenshot(true, "training-catalog-{$locale}-dark");
})->with(['en', 'ar'])->skip(fn (): bool => ! env('SCREENSHOTS'), 'Set SCREENSHOTS=1 to capture.');
