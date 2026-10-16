<?php

declare(strict_types=1);

use App\Domain\Access\Models\User;
use App\Domain\Forms\Actions\StoreFormUpload;
use App\Domain\Forms\Models\FormVersion;
use App\Domain\Hiring\Actions\AddToRequisition;
use App\Domain\Hiring\Actions\ChangePostingStatus;
use App\Domain\Hiring\Actions\ChangeRequisitionStatus;
use App\Domain\Hiring\Actions\MergeCandidates;
use App\Domain\Hiring\Actions\MoveApplication;
use App\Domain\Hiring\Actions\OpenRequisition;
use App\Domain\Hiring\Actions\RaiseRequisition;
use App\Domain\Hiring\Actions\RejectApplication;
use App\Domain\Hiring\Actions\ReopenApplication;
use App\Domain\Hiring\Actions\SaveJobPosting;
use App\Domain\Hiring\Actions\SavePipelineStages;
use App\Domain\Hiring\Actions\SeedHiringDefaults;
use App\Domain\Hiring\Actions\SubmitApplication;
use App\Domain\Hiring\Data\JobPostingInputData;
use App\Domain\Hiring\Enums\ApplicationSource;
use App\Domain\Hiring\Enums\ApplicationStatus;
use App\Domain\Hiring\Enums\MemberRole;
use App\Domain\Hiring\Enums\PostingStatus;
use App\Domain\Hiring\Enums\RejectionReason;
use App\Domain\Hiring\Enums\RequisitionStatus;
use App\Domain\Hiring\Enums\StageKind;
use App\Domain\Hiring\Models\ApplicationEvent;
use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\JobPosting;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Models\PipelineStage;
use App\Domain\Hiring\Models\RequisitionMember;
use App\Domain\Hiring\Queries\ApplicationsQuery;
use App\Domain\Hiring\Queries\CandidatesQuery;
use App\Domain\Hiring\Queries\RequisitionsQuery;
use App\Domain\Hiring\Support\HiringSettings;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Actions\SeedRequestTypes;
use App\Domain\Requests\Enums\RequestStatus;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Enums\Decision;
use App\Domain\Workflows\Enums\TaskStatus;
use App\Domain\Workflows\Models\WorkflowTask;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/*
| Phase 6a: requisitions through their approval chain, pipelines, postings, applications with
| duplicate detection and knock-outs, and who sees what (D80–D87).
*/
beforeEach(function (): void {
    Mail::fake();
    Storage::fake('local');
    seedAccess();
    resolve(SeedRequestTypes::class)->handle();
    resolve(SeedHiringDefaults::class)->handle();

    $this->branch = Branch::factory()->riyadh()->create();
    $this->otherBranch = Branch::factory()->riyadh()->create();
    $person = function (string $role, Branch $branch, ?Employee $manager = null, ?Department $department = null): array {
        $user = userWithRoles($role);

        return [$user, Employee::factory()->placedIn($branch, $department, $manager)->create(['user_id' => $user->id])];
    };

    [$this->directorUser, $director] = $person('employee', $this->branch);
    $this->department = Department::factory()->create(['head_employee_id' => $director->id]);
    [$this->hrUser] = $person('hr_officer', $this->branch, $director, $this->department);
    [$this->managerUser, $this->manager] = $person('branch_manager', $this->branch, $director, $this->department);
    [$this->otherManagerUser] = $person('branch_manager', $this->otherBranch);
    $this->recruiter = userWithRoles('recruiter');
    $this->interviewer = userWithRoles('interviewer');
});

function hiringDecide(EmployeeRequest $request, User $as, Decision $decision = Decision::Approved): void
{
    $task = WorkflowTask::query()->where('workflow_instance_id', $request->workflow_instance_id)->where('status', TaskStatus::Open->value)->sole();
    resolve(WorkflowEngine::class)->decide($task, $decision, $as, $decision === Decision::Approved ? null : 'Not this quarter');
}

function hiringRaise(object $test, array $overrides = []): JobRequisition
{
    return resolve(RaiseRequisition::class)->handle(Actor::user($test->managerUser), [
        'title' => 'Barista',
        'branch' => $test->branch->ulid,
        'department' => $test->department->ulid,
        'headcount' => 2,
        'reason' => 'new',
        'salary_min' => ['amount_minor' => 500000, 'currency' => 'SAR'],
        'salary_max' => ['amount_minor' => 650000, 'currency' => 'SAR'],
        'justification' => 'Weekend demand',
        ...$overrides,
    ]);
}

function hiringOpen(object $test): JobRequisition
{
    $requisition = hiringRaise($test);
    $request = EmployeeRequest::query()->findOrFail($requisition->request_id);
    hiringDecide($request, $test->directorUser);
    hiringDecide($request, $test->hrUser);

    return $requisition->fresh() ?? $requisition;
}

/**
 * @param  list<array{field: string, rule: mixed}>  $knockouts
 */
function hiringPosting(object $test, JobRequisition $requisition, array $knockouts = []): JobPosting
{
    $posting = resolve(SaveJobPosting::class)->handle(Actor::user($test->recruiter), $requisition, JobPostingInputData::from([
        'title' => ['en' => 'Barista', 'ar' => 'باريستا'],
        'description' => ['en' => 'Make great coffee.', 'ar' => 'حضّر قهوة رائعة.'],
        'knockouts' => $knockouts,
    ]));

    return resolve(ChangePostingStatus::class)->handle(Actor::user($test->recruiter), $posting, PostingStatus::Published);
}

function hiringCv(): string
{
    $form = resolve(HiringSettings::class)->defaultForm();
    $version = FormVersion::query()->findOrFail($form?->published_version_id);

    return resolve(StoreFormUpload::class)->handle($form, $version, 'cv', UploadedFile::fake()->create('cv.pdf', 40, 'application/pdf'), null, hash('sha256', 'visitor'))->ulid;
}

/**
 * @param  array<string, mixed>  $values
 */
function hiringApply(JobPosting $posting, array $values = [], bool $pool = false): JobApplication
{
    $requisition = $posting->requisition()->firstOrFail();

    return resolve(SubmitApplication::class)->handle(Actor::system('careers'), $requisition, $posting, [
        'full_name' => 'Lina Haddad',
        'email' => 'lina@example.com',
        'phone' => '+966500000001',
        'cv' => [hiringCv()],
        'right_to_work' => 'yes',
        ...$values,
    ], ApplicationSource::Careers, ['privacy' => true, 'talentPool' => $pool], hash('sha256', 'visitor'));
}

it('runs a requisition through its approval chain and opens it with the default pipeline', function (): void {
    $requisition = hiringRaise($this);

    expect($requisition->status)->toBe(RequisitionStatus::Pending)
        ->and($requisition->number)->toStartWith('JR-')
        ->and($requisition->branch_id)->toBe($this->branch->id)
        ->and($requisition->hiring_manager_employee_id)->toBe($this->manager->id)
        ->and($requisition->salary_min_minor)->toBe(500000)
        ->and($requisition->stages()->count())->toBe(0);

    $request = EmployeeRequest::query()->findOrFail($requisition->request_id);
    hiringDecide($request, $this->directorUser);
    hiringDecide($request, $this->hrUser);
    $requisition->refresh();

    expect($request->fresh()?->status)->toBe(RequestStatus::Approved)
        ->and($requisition->status)->toBe(RequisitionStatus::Open)
        ->and($requisition->stages()->pluck('kind')->map(fn (StageKind $k): string => $k->value)->all())->toBe(['applied', 'screening', 'interview', 'offer', 'hired'])
        ->and(RequisitionMember::query()->where('job_requisition_id', $requisition->id)->sole())
        ->user_id->toBe($this->managerUser->id)
        ->role->toBe(MemberRole::HiringManager);

    // Opening again (a replayed step) changes nothing.
    resolve(OpenRequisition::class)->handle(Actor::system('test'), $request);
    expect($requisition->stages()->count())->toBe(5);
});

it('ends the requisition when its approval is rejected', function (): void {
    $requisition = hiringRaise($this);
    hiringDecide(EmployeeRequest::query()->findOrFail($requisition->request_id), $this->directorUser, Decision::Rejected);

    expect($requisition->fresh()?->status)->toBe(RequisitionStatus::Rejected);
});

it('keeps a requisition type off the request center list', function (): void {
    $this->actingAs($this->directorUser)->get(route('requests.center'))
        ->assertInertia(fn ($page) => $page->where('types', fn ($types): bool => collect($types)->doesntContain('code', 'JOB_REQUISITION')));
});

it('receives an application, finds the same person by email and flags a shared phone', function (): void {
    $posting = hiringPosting($this, hiringOpen($this));
    $first = hiringApply($posting, pool: true);
    $candidate = $first->candidate()->firstOrFail();

    expect($first->status)->toBe(ApplicationStatus::Active)
        ->and($first->stage()->firstOrFail()->kind)->toBe(StageKind::Applied)
        ->and($first->cv_upload_id)->not->toBeNull()
        ->and($candidate->email)->toBe('lina@example.com')
        ->and($candidate->talent_pool)->toBeTrue()
        ->and($candidate->talent_pool_consented_at)->not->toBeNull()
        ->and($candidate->consented_at)->not->toBeNull();

    // Same email in another case: same candidate, same application refreshed.
    $again = hiringApply($posting, ['email' => 'LINA@Example.com', 'cv' => [hiringCv()]]);
    expect($again->id)->toBe($first->id)
        ->and(Candidate::query()->count())->toBe(1)
        ->and(ApplicationEvent::query()->where('job_application_id', $first->id)->count())->toBe(2);

    // Same phone, new email: a new candidate flagged as a possible duplicate.
    $other = hiringApply($posting, ['email' => 'lina.h@example.com', 'cv' => [hiringCv()]]);
    expect($other->candidate()->firstOrFail()->possible_duplicate_of_id)->toBe($candidate->id);
});

it('needs the privacy notice accepted on the careers site', function (): void {
    $posting = hiringPosting($this, hiringOpen($this));

    expect(fn () => resolve(SubmitApplication::class)->handle(Actor::system('careers'), $posting->requisition()->firstOrFail(), $posting, [
        'full_name' => 'Lina', 'email' => 'l@example.com', 'phone' => '+966500000001', 'cv' => [hiringCv()], 'right_to_work' => 'yes',
    ], ApplicationSource::Careers, ['privacy' => false, 'talentPool' => false], hash('sha256', 'visitor')))->toThrow(DomainRuleViolation::class);
});

it('flags knock-out answers without rejecting', function (): void {
    $requisition = hiringOpen($this);
    // Knock out anyone outside Riyadh.
    $posting = hiringPosting($this, $requisition, [
        ['field' => 'location', 'rule' => ['op' => 'neq', 'args' => [['ref' => 'field.location'], 'Riyadh']]],
    ]);

    $out = hiringApply($posting, ['location' => 'Dubai']);
    $in = hiringApply($posting, ['email' => 'sam@example.com', 'phone' => '+966500000002', 'location' => 'Riyadh', 'cv' => [hiringCv()]]);

    expect($out->knocked_out)->toBeTrue()
        ->and($out->knockout_reasons)->toBe([['field' => 'location', 'label' => ['en' => 'City', 'ar' => 'المدينة']]])
        ->and($out->status)->toBe(ApplicationStatus::Active)
        ->and($in->knocked_out)->toBeFalse();
});

it('rejects knock-outs that check another question or one not on the form', function (): void {
    $requisition = hiringOpen($this);

    expect(fn () => hiringPosting($this, $requisition, [['field' => 'nope', 'rule' => true]]))->toThrow(DomainRuleViolation::class)
        ->and(fn () => hiringPosting($this, $requisition, [['field' => 'location', 'rule' => ['op' => 'empty', 'args' => [['ref' => 'field.email']]]]]))->toThrow(DomainRuleViolation::class);
});

it('moves, rejects and reopens a candidate, keeping the history', function (): void {
    $application = hiringApply(hiringPosting($this, hiringOpen($this)));
    $stages = PipelineStage::query()->where('job_requisition_id', $application->job_requisition_id)->orderBy('position')->get();

    resolve(MoveApplication::class)->handle(Actor::user($this->recruiter), $application, $stages[2]);
    expect($application->fresh()?->pipeline_stage_id)->toBe($stages[2]->id);

    resolve(RejectApplication::class)->handle(Actor::user($this->recruiter), $application, RejectionReason::NotQualified, 'Not enough experience');
    expect($application->fresh())->status->toBe(ApplicationStatus::Rejected)->rejection_reason->toBe(RejectionReason::NotQualified)
        ->and(fn () => resolve(MoveApplication::class)->handle(Actor::user($this->recruiter), $application, $stages[3]))->toThrow(DomainRuleViolation::class);

    resolve(ReopenApplication::class)->handle(Actor::user($this->recruiter), $application);
    resolve(MoveApplication::class)->handle(Actor::user($this->recruiter), $application, $stages[4]);

    expect($application->fresh()?->status)->toBe(ApplicationStatus::Hired)
        ->and(ApplicationEvent::query()->where('job_application_id', $application->id)->pluck('kind')->map->value->all())
        ->toBe(['applied', 'moved', 'rejected', 'reopened', 'moved']);
});

it('freezes the board and takes postings down when the requisition stops', function (): void {
    $requisition = hiringOpen($this);
    $posting = hiringPosting($this, $requisition);
    $application = hiringApply($posting);

    resolve(ChangeRequisitionStatus::class)->handle(Actor::user($this->recruiter), $requisition, RequisitionStatus::OnHold);

    expect($posting->fresh()?->status)->toBe(PostingStatus::Closed)
        ->and(fn () => resolve(MoveApplication::class)->handle(Actor::user($this->recruiter), $application, PipelineStage::query()->where('job_requisition_id', $requisition->id)->where('kind', 'screening')->sole()))->toThrow(DomainRuleViolation::class)
        ->and(fn () => resolve(ChangePostingStatus::class)->handle(Actor::user($this->recruiter), $posting, PostingStatus::Published))->toThrow(DomainRuleViolation::class)
        ->and(fn () => resolve(ChangeRequisitionStatus::class)->handle(Actor::user($this->recruiter), $requisition, RequisitionStatus::Filled))->toThrow(DomainRuleViolation::class);
});

it('edits the stages but keeps applied first, hired last and occupied stages in place', function (): void {
    $application = hiringApply(hiringPosting($this, hiringOpen($this)));
    $requisition = $application->requisition()->firstOrFail();
    $stages = $requisition->stages()->get();
    $as = fn (PipelineStage $s): array => ['id' => $s->ulid, 'name' => $s->getTranslations('name'), 'kind' => $s->kind->value];
    $save = fn (array $list) => resolve(SavePipelineStages::class)->handle(Actor::user($this->recruiter), $requisition, $list);

    expect(fn () => $save([$as($stages[1]), $as($stages[0]), $as($stages[4])]))->toThrow(DomainRuleViolation::class)
        ->and(fn () => $save([$as($stages[1]), $as($stages[4])]))->toThrow(DomainRuleViolation::class);

    $save([$as($stages[0]), ['name' => ['en' => 'Phone screen'], 'kind' => 'screening'], $as($stages[2]), $as($stages[4])]);

    expect($requisition->stages()->get()->map(fn (PipelineStage $s): string => $s->getTranslation('name', 'en'))->all())
        ->toBe(['Applied', 'Phone screen', 'Interview', 'Hired'])
        ->and($requisition->stages()->get()[1]->getTranslation('name', 'ar'))->toBe('Phone screen');
});

it('merges a duplicate into the candidate kept and adds a pooled candidate to another job', function (): void {
    $posting = hiringPosting($this, hiringOpen($this));
    $keep = hiringApply($posting)->candidate()->firstOrFail();
    $duplicate = Candidate::query()->create(['name' => 'Lina H', 'email' => 'other@example.com', 'phone' => '+966500000001', 'source' => ApplicationSource::Manual, 'last_activity_at' => now(), 'possible_duplicate_of_id' => $keep->id]);

    // Both on the same job can't merge.
    resolve(AddToRequisition::class)->handle(Actor::user($this->recruiter), $duplicate, $posting->requisition()->firstOrFail());
    expect(fn () => resolve(MergeCandidates::class)->handle(Actor::user($this->recruiter), $keep, $duplicate))->toThrow(DomainRuleViolation::class);

    JobApplication::query()->where('candidate_id', $duplicate->id)->delete();
    $second = JobRequisition::factory()->create(['branch_id' => $this->branch->id]);
    PipelineStage::query()->create(['job_requisition_id' => $second->id, 'name' => ['en' => 'Applied'], 'kind' => StageKind::Applied, 'position' => 0]);
    resolve(AddToRequisition::class)->handle(Actor::user($this->recruiter), $duplicate, $second);
    resolve(MergeCandidates::class)->handle(Actor::user($this->recruiter), $keep, $duplicate);

    expect($duplicate->fresh()?->merged_into_id)->toBe($keep->id)
        ->and($keep->applications()->count())->toBe(2)
        ->and(resolve(CandidatesQuery::class)->visibleTo($this->recruiter)->pluck('id')->all())->toBe([$keep->id]);
});

/*
| Scope leaks (ADR-0005, D82): branch managers see their branch's requisitions; interviewers and
| other team members only the requisitions they work on; candidates follow their applications.
*/
it('scopes requisitions, applications and candidates to what the viewer covers', function (): void {
    $mine = hiringOpen($this);
    $theirs = JobRequisition::factory()->create(['branch_id' => $this->otherBranch->id]);
    PipelineStage::query()->create(['job_requisition_id' => $theirs->id, 'name' => ['en' => 'Applied'], 'kind' => StageKind::Applied, 'position' => 0]);
    $application = hiringApply(hiringPosting($this, $mine));
    resolve(AddToRequisition::class)->handle(Actor::user($this->recruiter), Candidate::factory()->create(), $theirs);

    $visible = fn (User $user, string $query): array => resolve($query)->visibleTo($user)->pluck(match ($query) {
        RequisitionsQuery::class => 'job_requisitions.id',
        ApplicationsQuery::class => 'job_applications.id',
        default => 'candidates.id',
    })->sort()->values()->all();

    expect($visible($this->recruiter, RequisitionsQuery::class))->toBe(collect([$mine->id, $theirs->id])->sort()->values()->all())
        ->and($visible($this->managerUser, RequisitionsQuery::class))->toBe([$mine->id])
        ->and($visible($this->otherManagerUser, RequisitionsQuery::class))->toBe([$theirs->id])
        ->and($visible($this->otherManagerUser, ApplicationsQuery::class))->not->toContain($application->id)
        ->and($visible($this->otherManagerUser, CandidatesQuery::class))->not->toContain($application->candidate_id)
        ->and($visible($this->interviewer, RequisitionsQuery::class))->toBe([])
        ->and($visible($this->interviewer, CandidatesQuery::class))->toBe([]);

    RequisitionMember::query()->create(['job_requisition_id' => $mine->id, 'user_id' => $this->interviewer->id, 'role' => MemberRole::Coordinator]);

    expect($visible($this->interviewer, RequisitionsQuery::class))->toBe([$mine->id])
        ->and($visible($this->interviewer, ApplicationsQuery::class))->toBe([$application->id])
        ->and($visible($this->interviewer, CandidatesQuery::class))->toBe([$application->candidate_id]);
});
