<?php

declare(strict_types=1);

use App\Domain\Access\Models\User;
use App\Domain\Attendance\Models\AttendanceDay;
use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Models\PipelineStage;
use App\Domain\Leave\Models\LeaveLedgerEntry;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\DocumentType;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\EmployeeDocument;
use App\Domain\Policies\Models\LeaveType;
use App\Domain\Reports\Actions\GenerateReportExport;
use App\Domain\Reports\Enums\RunStatus;
use App\Domain\Reports\Jobs\GenerateReportExportJob;
use App\Domain\Reports\Models\ReportRun;
use App\Domain\Reports\Support\ParamResolver;
use App\Domain\Reports\Support\ReportPreview;
use App\Domain\Reports\Support\ReportRegistry;
use App\Domain\Requests\Actions\SeedRequestTypes;
use App\Domain\Requests\Actions\SubmitRequest;
use App\Domain\Requests\Models\RequestType;
use App\Domain\Training\Enums\EnrollmentStatus;
use App\Domain\Training\Models\Certificate;
use App\Domain\Training\Models\Course;
use App\Domain\Training\Models\Enrollment;
use App\Support\Actors\Actor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/*
| Phase 8a: the report library and viewer (D112), every report scoped like the screens it comes
| from, and queued exports that only their owner can download (D113, D114).
*/
beforeEach(function (): void {
    Mail::fake();
    Storage::fake('local');
    seedAccess();
    resolve(SeedRequestTypes::class)->handle();
    $this->today = CarbonImmutable::now()->startOfDay();
    $this->documentType = DocumentType::query()->create(['code' => 'ID', 'name' => ['en' => 'ID card', 'ar' => 'الهوية']]);
    $this->unpaid = LeaveType::query()->create(['code' => 'UNPAID', 'name' => ['en' => 'Unpaid leave', 'ar' => 'إجازة بدون راتب'], 'unit' => 'days', 'is_paid' => false]);
    $this->course = Course::factory()->create(['title' => ['en' => 'Safety basics', 'ar' => 'أساسيات السلامة']]);
    $this->alpha = Branch::factory()->riyadh()->create(['name' => ['en' => 'Alpha branch', 'ar' => 'فرع ألفا']]);
    $this->bravo = Branch::factory()->riyadh()->create(['name' => ['en' => 'Bravo branch', 'ar' => 'فرع برافو']]);
    $this->managerUser = userWithRoles('branch_manager');
    Employee::factory()->placedIn($this->alpha)->create(['user_id' => $this->managerUser->id, 'name_en' => 'Alpha Manager']);
    $this->admin = userWithRoles('hr_admin');
    $this->admin->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();

    foreach (['Alpha' => $this->alpha, 'Bravo' => $this->bravo] as $label => $branch) {
        reportsWorld($this, $branch, $label);
    }
});

/** One person in a branch with something in every module: the markers each report looks for. */
function reportsWorld(object $test, Branch $branch, string $label): void
{
    $today = $test->today;
    $user = userWithRoles('employee');
    $person = Employee::factory()->placedIn($branch)->create(['user_id' => $user->id, 'name_en' => "{$label} Person", 'hire_date' => $today->subDays(3)->toDateString()]);

    EmployeeDocument::query()->create(['employee_id' => $person->id, 'document_type_id' => $test->documentType->id, 'expires_on' => $today->addDays(10)->toDateString()]);
    $leave = LeaveRequest::query()->create(['employee_id' => $person->id, 'kind' => 'leave', 'leave_type_id' => $test->unpaid->id, 'starts_on' => $today->subDay()->toDateString(), 'ends_on' => $today->subDay()->toDateString(), 'amount' => 1, 'status' => 'approved']);
    LeaveLedgerEntry::query()->create(['employee_id' => $person->id, 'leave_type_id' => $test->unpaid->id, 'year' => $today->year, 'kind' => 'debit', 'amount' => -1, 'effective_on' => $today->subDay()->toDateString(), 'leave_request_id' => $leave->id]);
    AttendanceDay::query()->create([
        'employee_id' => $person->id, 'branch_id' => $branch->id, 'date' => $today->subDays(2)->toDateString(), 'schedule_source' => 'policy', 'status' => 'late',
        'late_minutes' => 15, 'overtime_minutes' => 60, 'overtime_status' => 'approved', 'overtime_approved_minutes' => 30,
        'overtime_buckets' => ['workday' => ['multiplier' => 1.5, 'minutes' => 60]],
    ]);
    resolve(SubmitRequest::class)->handle(Actor::user($user), RequestType::query()->where('code', 'ASSET_REQUEST')->sole(), ['item' => 'laptop', 'details' => 'New']);

    $requisition = JobRequisition::factory()->create(['branch_id' => $branch->id, 'title' => ['en' => "{$label} role", 'ar' => "وظيفة {$label}"], 'opened_at' => $today->subDays(20)]);
    $hired = PipelineStage::query()->create(['job_requisition_id' => $requisition->id, 'name' => ['en' => 'Hired', 'ar' => 'تم التوظيف'], 'kind' => 'hired', 'position' => 4]);
    JobApplication::query()->create([
        'candidate_id' => Candidate::factory()->create()->id, 'job_requisition_id' => $requisition->id, 'pipeline_stage_id' => $hired->id, 'status' => 'hired', 'source' => 'careers',
        'applied_at' => $today->subDays(6), 'stage_entered_at' => $today->subDay(), 'last_activity_at' => $today->subDay(),
    ]);

    $enrollment = Enrollment::factory()->create(['employee_id' => $person->id, 'course_id' => $test->course->id, 'status' => EnrollmentStatus::Completed, 'completed_at' => now()]);
    Certificate::query()->create([
        'serial' => 'MW-2026-'.strtoupper(substr($label, 0, 1)).'2345678', 'employee_id' => $person->id, 'course_id' => $test->course->id, 'enrollment_id' => $enrollment->id,
        'title' => ['en' => 'Safety basics', 'ar' => 'أساسيات السلامة'], 'holder' => ['en' => "{$label} Person"], 'locale' => 'en',
        'issued_on' => $today->subDays(300)->toDateString(), 'expires_on' => $today->addDays(20)->toDateString(),
    ]);
}

/**
 * The report's rows and numbers as one string for this viewer.
 *
 * @param  array<string, mixed>  $input
 */
function reportJson(object $test, User $user, string $key, array $input = []): string
{
    $report = resolve(ReportRegistry::class)->find($key);
    $params = resolve(ParamResolver::class)->resolve($report, [
        'from' => $test->today->subDays(7)->toDateString(), 'to' => $test->today->toDateString(), 'month' => $test->today->format('Y-m'), ...$input,
    ]);

    return (string) json_encode(ReportPreview::build($report, $user, $params, 'en')->toArray());
}

dataset('scoped reports', [
    'headcount' => ['people.headcount', 'branch'],
    'joiners and leavers' => ['people.movements', 'person'],
    'turnover' => ['people.turnover', 'branch'],
    'documents expiring' => ['people.documents_expiring', 'person'],
    'leave balances' => ['leave.balances', 'person'],
    'leave taken' => ['leave.taken', 'person'],
    'attendance exceptions' => ['time.exceptions', 'person'],
    'overtime' => ['time.overtime', 'person'],
    'hiring funnel' => ['hiring.funnel', 'role'],
    'time to hire' => ['hiring.time_to_hire', 'role'],
    'certificates expiring' => ['training.certificates_expiring', 'person'],
    'payroll input' => ['payroll.input', 'person'],
]);

it('shows a branch manager only their branch, and HR everyone', function (string $key, string $marker): void {
    $needle = fn (string $label): string => match ($marker) {
        'branch' => "{$label} branch",
        'person' => "{$label} Person",
        'role' => "{$label} role",
    };
    // Payroll input is one branch at a time; ask for the other branch to prove it stays hidden.
    $alphaOnly = $key === 'payroll.input' ? ['branch' => $this->alpha->ulid] : [];

    $mine = reportJson($this, $this->managerUser, $key, $alphaOnly);
    expect($mine)->toContain($needle('Alpha'))->not->toContain($needle('Bravo'));

    if ($key === 'payroll.input') {
        expect(reportJson($this, $this->managerUser, $key, ['branch' => $this->bravo->ulid]))->not->toContain($needle('Bravo'));
        expect(reportJson($this, $this->admin, $key, ['branch' => $this->bravo->ulid]))->toContain($needle('Bravo'));
    } else {
        expect(reportJson($this, $this->admin, $key))->toContain($needle('Alpha'))->toContain($needle('Bravo'));
    }
})->with('scoped reports');

it('counts only what the viewer may see in grouped reports', function (): void {
    $count = function (User $user, string $key, string $column): int {
        $report = resolve(ReportRegistry::class)->find($key);
        $params = resolve(ParamResolver::class)->resolve($report, ['from' => $this->today->subDays(7)->toDateString(), 'to' => $this->today->addDay()->toDateString()]);

        return (int) array_sum(array_column(ReportPreview::build($report, $user, $params, 'en')->rows, $column));
    };

    expect($count($this->managerUser, 'requests.turnaround', 'submitted'))->toBe(1)
        ->and($count($this->admin, 'requests.turnaround', 'submitted'))->toBe(2)
        ->and($count($this->managerUser, 'training.completion', 'completed'))->toBe(1)
        ->and($count($this->admin, 'training.completion', 'completed'))->toBe(2);
});

it('works out payroll input from attendance, unpaid leave and counted overtime', function (): void {
    $report = resolve(ReportRegistry::class)->find('payroll.input');
    $params = resolve(ParamResolver::class)->resolve($report, ['month' => $this->today->format('Y-m'), 'branch' => $this->alpha->ulid]);
    $rows = collect(ReportPreview::build($report, $this->admin, $params, 'en')->rows)->keyBy('name');
    $person = $rows->get('Alpha Person');

    // 60 minutes worked, 30 approved: half of the workday bucket counts, at 1.5x.
    expect($person['late_minutes'])->toBe(15)
        ->and($person['overtime_workday'])->toBe(30)
        ->and($person['overtime_weighted_hours'])->toBe(0.75);

    // Unpaid leave counts only its working days in the month.
    $leaveDay = $this->today->subDay();
    expect($person['unpaid_leave_days'])->toBe($leaveDay->month === $this->today->month && ! in_array($leaveDay->dayOfWeekIso, [5, 6], true) ? 1.0 : 0.0);
});

it('lists the reports a person may open and keeps the rest out of reach', function (): void {
    $this->actingAs(userWithRoles('employee'))->get(route('reports.index'))->assertForbidden();

    $this->actingAs($this->managerUser)->get(route('reports.index'))
        ->assertInertia(fn (Assert $page): Assert => $page->component('reports/index')
            ->where('reports', fn ($reports): bool => collect($reports)->pluck('key')->contains('people.headcount') && collect($reports)->pluck('key')->doesntContain('admin.audit_log')));
    $this->actingAs($this->managerUser)->get(route('reports.show', 'admin.audit_log'))->assertNotFound();
    $this->actingAs($this->managerUser)->get(route('reports.show', 'people.nothing'))->assertNotFound();

    $auditor = userWithRoles('auditor');
    $auditor->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
    $this->actingAs($auditor)->get(route('reports.index'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('reports', fn ($reports): bool => collect($reports)->pluck('key')->contains('admin.audit_log')));
});

it('shows a report with its filters and loads the result after the page', function (): void {
    $this->actingAs($this->managerUser)->get(route('reports.show', ['report' => 'people.headcount']))
        ->assertInertia(fn (Assert $page): Assert => $page->component('reports/show')
            ->where('report.key', 'people.headcount')
            ->has('filters', 3)
            ->missing('result')
            ->loadDeferredProps(fn (Assert $reload): Assert => $reload->where('result.total', 1)->where('result.kpis.0.value', 2)->where('result.rows.0.branch', 'Alpha branch')));

    // A bad link keeps the page working and says what's wrong.
    $this->actingAs($this->managerUser)->get(route('reports.show', ['report' => 'people.movements', 'from' => '2026-05-01', 'to' => '2026-01-01']))
        ->assertInertia(fn (Assert $page): Assert => $page->has('filterErrors.from'));

    // Payroll input waits for a branch.
    $this->actingAs($this->managerUser)->get(route('reports.show', 'payroll.input'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('result', null)->where('filterErrors', []));
});

it('makes an export on the queue that only its owner can download', function (): void {
    $this->actingAs($this->managerUser)->post(route('reports.exports.store', 'people.documents_expiring'), ['format' => 'xlsx', 'days' => 30])
        ->assertRedirect(route('reports.exports.index'));

    $run = ReportRun::query()->sole();
    expect($run->status)->toBe(RunStatus::Ready)
        ->and($run->rows)->toBe(1)
        ->and($run->params['days'])->toBe(30)
        ->and($run->expires_at?->isAfter(now()->addDays(6)))->toBeTrue();
    Storage::disk('local')->assertExists((string) $run->file_path);
    expect($this->managerUser->notifications()->sole()->data['event'] ?? null)->toBe('reports.export_ready');

    $this->actingAs($this->managerUser)->get(route('reports.exports.index'))
        ->assertInertia(fn (Assert $page): Assert => $page->component('reports/exports')->where('exports.0.status', 'ready')->where('exports.0.rows', 1));
    $this->actingAs($this->managerUser)->get(route('reports.exports.download', $run))->assertOk()->assertDownload((string) $run->file_name);
    $this->actingAs($this->admin)->get(route('reports.exports.download', $run))->assertNotFound();

    expect(Activity::query()->where('log_name', 'reports')->pluck('event')->all())
        ->toEqualCanonicalizing(['export_requested', 'exported', 'downloaded']);
});

it('writes CSV with a BOM and neutralises formulas', function (): void {
    Employee::query()->where('name_en', 'Alpha Person')->update(['preferred_name' => '=HYPERLINK("x")']);

    $this->actingAs($this->managerUser)->post(route('reports.exports.store', 'people.documents_expiring'), ['format' => 'csv'])->assertRedirect();
    $csv = Storage::disk('local')->get((string) ReportRun::query()->sole()->file_path);

    expect($csv)->toStartWith("\xEF\xBB\xBF")
        ->toContain('Employee no.')
        ->toContain("'=HYPERLINK");
});

it('refuses exports to people who may only view, and when too many are waiting', function (): void {
    $viewer = userWithRoles('department_manager');
    $this->actingAs($viewer)->post(route('reports.exports.store', 'people.headcount'), ['format' => 'xlsx'])->assertForbidden();

    Queue::fake();
    foreach (range(1, 5) as $n) {
        $this->actingAs($this->managerUser)->post(route('reports.exports.store', 'people.headcount'), ['format' => 'csv'])->assertRedirect();
    }

    $this->actingAs($this->managerUser)->post(route('reports.exports.store', 'people.headcount'), ['format' => 'csv'])->assertSessionHasErrors();
    expect(ReportRun::query()->count())->toBe(5);
    Queue::assertPushed(GenerateReportExportJob::class, 5);
});

it('fails an export when its owner lost access before it ran', function (): void {
    Queue::fake();
    $this->actingAs($this->managerUser)->post(route('reports.exports.store', 'people.headcount'), ['format' => 'xlsx'])->assertRedirect();
    $this->managerUser->syncRoles(['employee']);
    // A queue worker starts each job with fresh request-scoped services, as here.
    app()->forgetScopedInstances();

    resolve(GenerateReportExport::class)->handle(ReportRun::query()->sole());

    $run = ReportRun::query()->sole();
    expect($run->status)->toBe(RunStatus::Failed)->and($run->file_path)->toBeNull()->and($run->error)->toContain('no longer have access');
});

it('deletes exports after a week and fails ones a worker lost', function (): void {
    Storage::disk('local')->put('reports/old.xlsx', 'x');
    $old = ReportRun::query()->create(['user_id' => $this->managerUser->id, 'report' => 'people.headcount', 'params' => [], 'format' => 'xlsx', 'status' => RunStatus::Ready, 'file_path' => 'reports/old.xlsx', 'expires_at' => now()->subMinute()]);
    $lost = ReportRun::query()->create(['user_id' => $this->managerUser->id, 'report' => 'people.headcount', 'params' => [], 'format' => 'xlsx', 'status' => RunStatus::Running]);
    $lost->forceFill(['created_at' => now()->subHours(3)])->save();

    $this->artisan('reports:prune')->assertSuccessful();

    expect(ReportRun::query()->find($old->id))->toBeNull()
        ->and($lost->fresh()?->status)->toBe(RunStatus::Failed);
    Storage::disk('local')->assertMissing('reports/old.xlsx');
});
