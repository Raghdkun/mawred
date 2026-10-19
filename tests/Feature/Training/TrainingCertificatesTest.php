<?php

declare(strict_types=1);

use App\Domain\Access\Models\User;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Actions\SeedRequestTypes;
use App\Domain\Requests\Actions\SubmitRequest;
use App\Domain\Requests\Models\RequestType;
use App\Domain\Training\Actions\ChangeCourseStatus;
use App\Domain\Training\Actions\Enrol;
use App\Domain\Training\Actions\SaveCourse;
use App\Domain\Training\Actions\SaveCourseModules;
use App\Domain\Training\Actions\SeedTrainingDefaults;
use App\Domain\Training\Data\CourseInputData;
use App\Domain\Training\Data\ModulesInputData;
use App\Domain\Training\Enums\CourseStatus;
use App\Domain\Training\Enums\EnrollmentSource;
use App\Domain\Training\Models\Certificate;
use App\Domain\Training\Models\Enrollment;
use App\Domain\Training\Models\ExternalTraining;
use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Enums\Decision;
use App\Domain\Workflows\Enums\TaskStatus;
use App\Domain\Workflows\Models\WorkflowTask;
use App\Support\Actors\Actor;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Phase 7b: certificates on completion (D103, D110), the public check (D104), expiry reminders
| and renewal (D103), and training requests on the training record (D105).
*/
beforeEach(function (): void {
    Mail::fake();
    Storage::fake('local');
    Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.4 certificate', 200, ['Content-Type' => 'application/pdf'])]);
    seedAccess();
    resolve(SeedTrainingDefaults::class)->handle();
    $this->branch = Branch::factory()->riyadh()->create();
    $this->trainer = userWithRoles('trainer');
    $this->managerUser = userWithRoles('branch_manager');
    $this->manager = Employee::factory()->placedIn($this->branch)->create(['user_id' => $this->managerUser->id]);
    $this->learnerUser = userWithRoles('employee');
    $this->learner = Employee::factory()->placedIn($this->branch, null, $this->manager)->create(['user_id' => $this->learnerUser->id, 'name_en' => 'Lina Haddad']);

    $course = resolve(SaveCourse::class)->handle(Actor::user($this->trainer), CourseInputData::from(['title' => ['en' => 'First aid', 'ar' => 'الإسعافات الأولية'], 'validityMonths' => 12]));
    resolve(SaveCourseModules::class)->handle(Actor::user($this->trainer), $course, ModulesInputData::from(['modules' => [['kind' => 'text', 'title' => ['en' => 'Basics']]]]));
    $this->course = resolve(ChangeCourseStatus::class)->handle(Actor::user($this->trainer), $course->fresh() ?? $course, CourseStatus::Published);
});

function trainingComplete(object $test): Enrollment
{
    $enrollment = resolve(Enrol::class)->handle(Actor::user($test->trainer), $test->learner, $test->course, EnrollmentSource::Assigned);
    $test->actingAs($test->learnerUser)->post(route('training.learn.complete', [$test->course, $test->course->modules()->sole()]));

    return $enrollment->fresh() ?? $enrollment;
}

it('issues a certificate on completion that anyone can check and HR can revoke', function (): void {
    trainingComplete($this);
    $certificate = Certificate::query()->sole();
    $today = CarbonImmutable::now('Asia/Riyadh')->toDateString();

    expect($certificate->serial)->toMatch('/^MW-\d{4}-[2-9A-Z]{8}$/')
        ->and($certificate->getTranslation('holder', 'en'))->toBe('Lina Haddad')
        ->and($certificate->issued_on->toDateString())->toBe($today)
        ->and($certificate->expires_on?->toDateString())->toBe(CarbonImmutable::parse($today)->addMonthsNoOverflow(12)->toDateString());

    // The PDF prints once on A4 landscape, and only the holder and people over them get it.
    $this->get(route('training.certificates.pdf', $certificate))->assertOk()->assertDownload("{$certificate->serial}.pdf");
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'chromium') && str_contains((string) $request->body(), '11.7'));
    $this->actingAs(userWithRoles('employee'))->get(route('training.certificates.pdf', $certificate))->assertNotFound();

    // The public check shows the name, course, dates and status, nothing more.
    auth()->logout();
    $this->get(route('certificates.verify', strtolower($certificate->serial)))
        ->assertInertia(fn (Assert $page): Assert => $page->component('public/certificate')->where('certificate.holder', 'Lina Haddad')->where('certificate.state', 'valid')->missing('certificate.employeeNumber'));
    $this->get(route('certificates.verify', 'MW-2026-NOPENOPE'))->assertInertia(fn (Assert $page): Assert => $page->where('certificate', null));

    $hr = userWithRoles('hr_admin');
    $hr->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
    $this->actingAs($this->learnerUser)->post(route('training.certificates.revoke', $certificate), ['reason' => 'x'])->assertNotFound();
    $this->actingAs($hr)->post(route('training.certificates.revoke', $certificate), ['reason' => 'Issued by mistake'])->assertRedirect();
    $this->get(route('certificates.verify', $certificate->serial))->assertInertia(fn (Assert $page): Assert => $page->where('certificate.state', 'revoked'));
});

it('reminds before expiry and enrols the holder again to renew', function (): void {
    trainingComplete($this);
    $certificate = Certificate::query()->sole();
    $certificate->forceFill(['expires_on' => CarbonImmutable::now('Asia/Riyadh')->addDays(25)->toDateString()])->save();

    $this->artisan('training:certificates')->assertSuccessful();
    $certificate->refresh();
    $renewal = Enrollment::query()->where('source', 'renewal')->sole();

    expect($certificate->reminders_sent)->toEqualCanonicalizing([60, 30])
        ->and($certificate->renewal_enrollment_id)->toBe($renewal->id)
        ->and($renewal->due_on?->toDateString())->toBe($certificate->expires_on?->toDateString());

    // Running again sends nothing new; at seven days the last reminder goes.
    $this->artisan('training:certificates')->assertSuccessful();
    expect(Enrollment::query()->where('source', 'renewal')->count())->toBe(1);
    $certificate->forceFill(['expires_on' => CarbonImmutable::now('Asia/Riyadh')->addDays(5)->toDateString()])->save();
    $this->artisan('training:certificates')->assertSuccessful();
    expect($certificate->fresh()?->reminders_sent)->toEqualCanonicalizing([60, 30, 7]);
});

it('puts an approved training request on the record, with its certificate or a catalog course', function (): void {
    resolve(SeedRequestTypes::class)->handle();
    userWithRoles('hr_admin'); // the standard chain's HR step falls back to HR admins
    $type = RequestType::query()->where('code', 'TRAINING')->sole();
    expect($type->isAvailable())->toBeTrue();

    $request = resolve(SubmitRequest::class)->handle(Actor::user($this->learnerUser), $type, [
        'course' => 'Advanced latte art', 'provider' => 'Coffee Academy', 'dates' => ['from' => '2026-12-01', 'to' => '2026-12-03'],
        'cost' => ['amount_minor' => 150000, 'currency' => 'SAR'],
    ]);

    while ($task = WorkflowTask::query()->where('workflow_instance_id', $request->fresh()?->workflow_instance_id)->where('status', TaskStatus::Open->value)->first()) {
        resolve(WorkflowEngine::class)->decide($task, Decision::Approved, User::query()->findOrFail($task->assignee_user_id));
    }

    $record = ExternalTraining::query()->sole();
    expect($record)->title->toBe('Advanced latte art')->provider->toBe('Coffee Academy')
        ->and($record->starts_on?->toDateString())->toBe('2026-12-01')
        ->and($record->cost?->minor)->toBe(150000);

    // The learner adds the certificate; HR could instead meet it with a catalog course.
    $this->actingAs($this->learnerUser)->post(route('training.external.upload', $record), ['file' => UploadedFile::fake()->create('cert.pdf', 40, 'application/pdf')])->assertRedirect();
    expect($record->fresh()?->certificate_name)->toBe('cert.pdf');
    $this->get(route('training.external.certificate', $record))->assertOk();
    $this->post(route('training.external.link', $record), ['course' => $this->course->ulid])->assertNotFound();

    $this->actingAs($this->trainer)->post(route('training.external.link', $record), ['course' => $this->course->ulid])->assertRedirect();
    expect(Enrollment::query()->where('source', 'request')->sole()->id)->toBe($record->fresh()?->enrollment_id);

    $this->actingAs($this->managerUser)->getJson(route('api.v1.training.record', $this->learner))->assertOk()
        ->assertJsonPath('data.external.0.title', 'Advanced latte art')->assertJsonPath('data.external.0.course.id', $this->course->ulid);
});
