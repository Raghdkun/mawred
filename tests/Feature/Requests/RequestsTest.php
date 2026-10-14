<?php

declare(strict_types=1);

use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Actions\ResubmitRequest;
use App\Domain\Requests\Actions\SubmitRequest;
use App\Domain\Requests\Actions\WithdrawRequest;
use App\Domain\Requests\Enums\RequestStatus;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Requests\Queries\RequestDirectoryQuery;
use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Enums\Decision;
use App\Domain\Workflows\Enums\TaskStatus;
use App\Domain\Workflows\Models\WorkflowTask;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    seedAccess();
    $this->riyadh = Branch::factory()->riyadh()->create();
    $this->dubai = Branch::factory()->create();

    $this->managerUser = userWithRoles('employee');
    $manager = Employee::factory()->placedIn($this->riyadh)->create(['user_id' => $this->managerUser->id]);
    $this->requesterUser = userWithRoles('employee');
    $this->requester = Employee::factory()->placedIn($this->riyadh, null, $manager)->create(['user_id' => $this->requesterUser->id]);

    $this->type = tripRequestType();
});

function submitTrip(object $test, array $values = ['destination' => 'Jeddah']): EmployeeRequest
{
    return resolve(SubmitRequest::class)->handle(Actor::user($test->requesterUser), $test->type, $values);
}

function managerTask(EmployeeRequest $request): WorkflowTask
{
    return WorkflowTask::query()->where('workflow_instance_id', $request->workflow_instance_id)->where('status', TaskStatus::Open->value)->sole();
}

it('submits a request through its form and workflow to approval', function (): void {
    $request = submitTrip($this);

    expect($request)->number->toMatch('/^REQ-\d{6}$/')->status->toBe(RequestStatus::InReview)->branch_id->toBe($this->riyadh->id)
        ->and($request->submission()->sole()->data)->toBe(['destination' => 'Jeddah'])
        ->and(managerTask($request)->assignee_user_id)->toBe($this->managerUser->id);

    resolve(WorkflowEngine::class)->decide(managerTask($request), Decision::Approved, $this->managerUser);

    expect($request->fresh())->status->toBe(RequestStatus::Approved)->decided_at->not->toBeNull();
});

it('validates answers before anything is created', function (): void {
    expect(fn () => submitTrip($this, []))->toThrow(ValidationException::class)
        ->and(EmployeeRequest::query()->count())->toBe(0);
});

it('goes back to the requester for changes and returns to review on resubmission', function (): void {
    $request = submitTrip($this);
    resolve(WorkflowEngine::class)->decide(managerTask($request), Decision::Returned, $this->managerUser, 'Which city?');
    expect($request->fresh()?->status)->toBe(RequestStatus::Returned);

    resolve(ResubmitRequest::class)->handle(Actor::user($this->requesterUser), $request->fresh(), ['destination' => 'Abha']);

    expect($request->fresh())->status->toBe(RequestStatus::InReview)
        ->and($request->fresh()?->submission()->sole()->data)->toBe(['destination' => 'Abha'])
        ->and(managerTask($request)->ownerStep()->iteration)->toBe(2);
});

it('lets the requester withdraw an open request, and only then', function (): void {
    $request = submitTrip($this);
    resolve(WithdrawRequest::class)->handle(Actor::user($this->requesterUser), $request);

    expect($request->fresh()?->status)->toBe(RequestStatus::Cancelled)
        ->and(fn () => resolve(WithdrawRequest::class)->handle(Actor::user($this->requesterUser), $request->fresh()))->toThrow(DomainRuleViolation::class);
});

it('keeps request types from later phases unavailable', function (): void {
    $this->type->forceFill(['available_in_phase' => 5])->save();

    expect(fn () => submitTrip($this))->toThrow(DomainRuleViolation::class, 'not available');
});

it('scopes request lists by the requester placement and lets assignees open what they decide', function (): void {
    $request = submitTrip($this);
    $dubaiUser = userWithRoles('employee');
    $dubaiEmployee = Employee::factory()->placedIn($this->dubai)->create(['user_id' => $dubaiUser->id]);
    $dubaiRequest = EmployeeRequest::query()->create([...$request->only(['request_type_id', 'form_submission_id', 'status', 'submitted_at']), 'number' => 'REQ-999999', 'requester_employee_id' => $dubaiEmployee->id, 'requester_user_id' => $dubaiUser->id, 'branch_id' => $this->dubai->id]);

    $officer = userWithRoles('hr_officer');
    Employee::factory()->placedIn($this->riyadh)->create(['user_id' => $officer->id]);

    $visible = resolve(RequestDirectoryQuery::class)->visibleTo($officer)->pluck('number')->all();
    expect($visible)->toBe([$request->number])
        ->and(Gate::forUser($officer)->allows('view', $dubaiRequest))->toBeFalse()
        ->and(Gate::forUser($this->managerUser)->allows('view', $request))->toBeTrue()
        ->and(Gate::forUser($dubaiUser)->allows('view', $request))->toBeFalse();
});
