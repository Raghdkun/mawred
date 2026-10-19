<?php

declare(strict_types=1);

use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Actions\SeedRequestTypes;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Requests\Models\RequestType;
use App\Domain\Workflows\Enums\TaskStatus;
use App\Domain\Workflows\Models\WorkflowTask;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    Mail::fake();
    seedAccess();
    resolve(SeedRequestTypes::class)->handle();
    $branch = Branch::factory()->riyadh()->create();
    $this->managerUser = userWithRoles('employee');
    $manager = Employee::factory()->placedIn($branch)->create(['user_id' => $this->managerUser->id]);
    $this->requesterUser = userWithRoles('employee');
    Employee::factory()->placedIn($branch, null, $manager)->create(['user_id' => $this->requesterUser->id]);
    $this->stranger = userWithRoles('employee');
    Employee::factory()->placedIn($branch)->create(['user_id' => $this->stranger->id]);
    $this->asset = RequestType::query()->where('code', 'ASSET_REQUEST')->sole();
});

it('lists the request types someone can use, with the form on show', function (): void {
    Sanctum::actingAs($this->requesterUser);

    $this->getJson('/api/v1/request-types')->assertOk()->assertJsonCount(15, 'data')->assertJsonMissingPath('data.0.form');
    $this->getJson("/api/v1/request-types/{$this->asset->ulid}")->assertOk()->assertJsonPath('data.code', 'ASSET_REQUEST')->assertJsonPath('data.form.format', 'mawred.form/v1');

    // A type flagged for a later phase isn't offered.
    $training = RequestType::query()->where('code', 'TRAINING')->sole();
    $training->forceFill(['available_in_phase' => 99])->save();
    $this->getJson("/api/v1/request-types/{$training->ulid}")->assertNotFound();
});

it('submits once per idempotency key and validates with the error envelope', function (): void {
    Sanctum::actingAs($this->requesterUser);
    $url = "/api/v1/request-types/{$this->asset->ulid}/requests";

    $this->postJson($url, ['values' => ['item' => 'drone']])->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed')->assertJsonStructure(['error' => ['details' => ['item', 'details']]]);

    $first = $this->postJson($url, ['values' => ['item' => 'laptop', 'details' => 'New hire']], ['Idempotency-Key' => 'abc-123'])->assertCreated();
    $again = $this->postJson($url, ['values' => ['item' => 'laptop', 'details' => 'New hire']], ['Idempotency-Key' => 'abc-123'])->assertCreated();

    expect($again->json('data.id'))->toBe($first->json('data.id'))
        ->and(EmployeeRequest::query()->count())->toBe(1);
});

it('shows only the caller’s own requests and hides others with 404', function (): void {
    Sanctum::actingAs($this->requesterUser);
    $id = $this->postJson("/api/v1/request-types/{$this->asset->ulid}/requests", ['values' => ['item' => 'phone', 'details' => 'Broken']])->json('data.id');

    $this->getJson('/api/v1/requests')->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.answers');
    $this->getJson("/api/v1/requests/{$id}")->assertOk()->assertJsonPath('data.answers.item', 'phone');

    Sanctum::actingAs($this->stranger);
    $this->getJson('/api/v1/requests')->assertJsonCount(0, 'data');
    $this->getJson("/api/v1/requests/{$id}")->assertNotFound()->assertJsonPath('error.code', 'not_found');
});

it('lists and decides approvals for the assignee only', function (): void {
    Sanctum::actingAs($this->requesterUser);
    $this->postJson("/api/v1/request-types/{$this->asset->ulid}/requests", ['values' => ['item' => 'monitor', 'details' => 'Second screen']]);
    $task = WorkflowTask::query()->where('status', TaskStatus::Open->value)->sole();

    Sanctum::actingAs($this->stranger);
    $this->postJson("/api/v1/approvals/{$task->ulid}/decision", ['decision' => 'approved'])->assertNotFound();

    Sanctum::actingAs($this->managerUser);
    $this->getJson('/api/v1/approvals')->assertOk()->assertJsonPath('data.0.id', $task->ulid)->assertJsonPath('data.0.request.number', EmployeeRequest::query()->value('number'));
    $this->postJson("/api/v1/approvals/{$task->ulid}/decision", ['decision' => 'rejected'])->assertUnprocessable();
    $this->postJson("/api/v1/approvals/{$task->ulid}/decision", ['decision' => 'approved'])->assertOk()->assertJsonPath('data.decisions', []);

    expect($task->fresh()?->decided_via?->value)->toBe('api');
});
