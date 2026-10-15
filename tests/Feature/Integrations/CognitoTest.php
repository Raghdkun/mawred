<?php

declare(strict_types=1);

use App\Domain\Integrations\Actions\AssignCognitoReceipt;
use App\Domain\Integrations\Actions\ReplayCognitoReceipt;
use App\Domain\Integrations\Actions\SaveCognitoSource;
use App\Domain\Integrations\Data\CognitoSourceData;
use App\Domain\Integrations\Enums\ReceiptStatus;
use App\Domain\Integrations\Models\CognitoReceipt;
use App\Domain\Integrations\Models\CognitoSource;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Actions\SeedRequestTypes;
use App\Domain\Requests\Enums\RequestStatus;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Requests\Models\RequestType;
use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Enums\Decision;
use App\Domain\Workflows\Enums\TaskStatus;
use App\Domain\Workflows\Models\WorkflowTask;
use App\Support\Actors\Actor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    Mail::fake();
    Storage::fake('local');
    Http::fake(['files.cognitoforms.com/*' => Http::response('%PDF-1.4 receipt', 200, ['Content-Type' => 'application/pdf'])]);
    seedAccess();
    resolve(SeedRequestTypes::class)->handle();
    $branch = Branch::factory()->riyadh()->create();
    $this->managerUser = userWithRoles('employee');
    $manager = Employee::factory()->placedIn($branch)->create(['user_id' => $this->managerUser->id]);
    $this->requester = Employee::factory()->placedIn($branch, null, $manager)->create(['work_email' => 'rami@najd.example']);
    $this->type = RequestType::query()->where('code', 'EXPENSE')->sole();

    [$this->source, $this->token] = resolve(SaveCognitoSource::class)->handle(Actor::system('test'), CognitoSourceData::from([
        'name' => 'Expenses (Cognito)', 'requestTypeId' => $this->type->ulid, 'formUrl' => 'https://www.cognitoforms.com/Najd/Expenses',
        'fields' => [['from' => 'Description', 'to' => 'description'], ['from' => 'SpentOn', 'to' => 'spent_on'], ['from' => 'Receipts', 'to' => 'receipts']],
        'requesterBy' => 'email', 'requesterPath' => 'Email', 'entryPath' => null, 'versionPath' => null,
        'prefill' => ['email' => 'Email', 'name' => 'Name.First'], 'isActive' => true,
    ]));
    $this->payload = json_decode((string) file_get_contents(base_path('tests/fixtures/cognito/expense-submit.json')), true);
    $this->payload['Amount'] = null;
});

function postCognito(object $test, string $event, array $payload, ?string $token = null): TestResponse
{
    return $test->postJson('/hooks/cognito/'.($token ?? $test->token)."/{$event}", $payload);
}

it('turns a submitted entry into the matched employee’s request, files included', function (): void {
    $this->source->forceFill(['mapping' => [...$this->source->mapping, 'fields' => [...$this->source->mapping['fields'], ['from' => 'Money', 'to' => 'amount']]]])->save();
    $payload = [...$this->payload, 'Money' => ['amount_minor' => 4500, 'currency' => 'SAR']];

    postCognito($this, 'submit', $payload)->assertOk()->assertJsonStructure(['received']);

    $request = EmployeeRequest::query()->sole();
    expect($request)->requester_employee_id->toBe($this->requester->id)->external_entry_id->toBe('7-42')->status->toBe(RequestStatus::InReview)
        ->and($request->submission()->sole()->data)->toMatchArray(['description' => 'Taxi to the client', 'spent_on' => '2026-10-12'])
        ->and($request->submission()->sole()->data['receipts'][0]['name'])->toBe('receipt.pdf')
        ->and(CognitoReceipt::query()->sole())->status->toBe(ReceiptStatus::Processed)->request_id->toBe($request->id)
        ->and($this->type->fresh()?->source)->toBe('cognito');
});

it('rejects unknown tokens with 404 and bad bodies with 400', function (): void {
    postCognito($this, 'submit', $this->payload, str_repeat('x', 48))->assertNotFound();
    $this->call('POST', "/hooks/cognito/{$this->token}/submit", [], [], [], ['CONTENT_TYPE' => 'application/json'], 'not json')->assertStatus(400);

    expect(CognitoReceipt::query()->count())->toBe(0);
});

it('keeps a retried post but processes it once', function (): void {
    $payload = [...$this->payload, 'Money' => ['amount_minor' => 4500, 'currency' => 'SAR']];
    $this->source->forceFill(['mapping' => [...$this->source->mapping, 'fields' => [...$this->source->mapping['fields'], ['from' => 'Money', 'to' => 'amount']]]])->save();

    postCognito($this, 'submit', $payload)->assertOk();
    postCognito($this, 'submit', $payload)->assertOk();

    expect(EmployeeRequest::query()->count())->toBe(1)
        ->and(CognitoReceipt::query()->orderBy('id')->pluck('status')->map->value->all())->toBe(['processed', 'duplicate']);
});

it('applies updates while open, logs late updates, and cancels on delete', function (): void {
    $this->source->forceFill(['mapping' => [...$this->source->mapping, 'fields' => [...$this->source->mapping['fields'], ['from' => 'Money', 'to' => 'amount']]]])->save();
    $payload = [...$this->payload, 'Money' => ['amount_minor' => 4500, 'currency' => 'SAR']];
    postCognito($this, 'submit', $payload);
    $request = EmployeeRequest::query()->sole();

    postCognito($this, 'update', [...$payload, 'Description' => 'Taxi and parking', 'Entry' => [...$payload['Entry'], 'Version' => 2]])->assertOk();
    expect($request->fresh()?->submission()->sole()->data['description'])->toBe('Taxi and parking');

    postCognito($this, 'delete', $payload)->assertOk();
    expect($request->fresh()?->status)->toBe(RequestStatus::Cancelled);

    postCognito($this, 'update', [...$payload, 'Entry' => [...$payload['Entry'], 'Version' => 3]]);
    expect(CognitoReceipt::query()->latest('id')->first()?->status)->toBe(ReceiptStatus::LateUpdate);
});

it('holds entries nobody matches until an admin picks the person (D61)', function (): void {
    $this->source->forceFill(['mapping' => [...$this->source->mapping, 'fields' => [...$this->source->mapping['fields'], ['from' => 'Money', 'to' => 'amount']]]])->save();
    postCognito($this, 'submit', [...$this->payload, 'Email' => 'stranger@example.com', 'Money' => ['amount_minor' => 900, 'currency' => 'SAR']]);

    $receipt = CognitoReceipt::query()->sole();
    expect($receipt->status)->toBe(ReceiptStatus::NeedsPerson)->and(EmployeeRequest::query()->count())->toBe(0);

    resolve(AssignCognitoReceipt::class)->handle(Actor::system('test'), $receipt, $this->requester);

    expect($receipt->fresh()?->status)->toBe(ReceiptStatus::Processed)
        ->and(EmployeeRequest::query()->sole()->requester_employee_id)->toBe($this->requester->id);
});

it('records validation failures so they can be fixed and replayed', function (): void {
    postCognito($this, 'submit', $this->payload);
    $receipt = CognitoReceipt::query()->sole();

    expect($receipt->status)->toBe(ReceiptStatus::Failed)->and($receipt->error)->toContain('amount');

    $this->source->forceFill(['mapping' => [...$this->source->mapping, 'fields' => [...$this->source->mapping['fields'], ['from' => 'Money', 'to' => 'amount']]]])->save();
    $receipt->forceFill(['payload' => [...$this->payload, 'Money' => ['amount_minor' => 4500, 'currency' => 'SAR']]])->save();
    resolve(ReplayCognitoReceipt::class)->handle(Actor::system('test'), $receipt);

    expect($receipt->fresh()?->status)->toBe(ReceiptStatus::Processed);
});

it('stores the next entry as the sample while capturing', function (): void {
    $this->source->forceFill(['capture_sample' => true])->save();
    postCognito($this, 'submit', $this->payload);

    expect($this->source->fresh())->capture_sample->toBeFalse()->sample_payload->toMatchArray(['Email' => 'rami@najd.example'])
        ->and(CognitoReceipt::query()->sole()->status)->toBe(ReceiptStatus::Sample)
        ->and(EmployeeRequest::query()->count())->toBe(0);
});

it('gives the request the normal approval flow', function (): void {
    $this->source->forceFill(['mapping' => [...$this->source->mapping, 'fields' => [...$this->source->mapping['fields'], ['from' => 'Money', 'to' => 'amount']]]])->save();
    postCognito($this, 'submit', [...$this->payload, 'Money' => ['amount_minor' => 4500, 'currency' => 'SAR']]);

    $task = WorkflowTask::query()->where('status', TaskStatus::Open->value)->sole();
    expect($task->assignee_user_id)->toBe($this->managerUser->id);
    resolve(WorkflowEngine::class)->decide($task, Decision::Approved, $this->managerUser);
});

it('shows the webhook URLs once on creation and masks them afterwards', function (): void {
    $admin = userWithRoles('hr_admin');
    $admin->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();

    $response = $this->actingAs($admin)->post(route('integrations.cognito.store'), [
        'name' => 'Leave (Cognito)', 'requestTypeId' => null, 'formUrl' => null, 'fields' => [], 'requesterBy' => 'email', 'requesterPath' => 'Email',
        'entryPath' => null, 'versionPath' => null, 'prefill' => [], 'isActive' => true,
    ]);
    $source = CognitoSource::query()->where('name', 'Leave (Cognito)')->sole();
    $response->assertRedirect(route('integrations.cognito.show', $source));

    $this->actingAs($admin)->get(route('integrations.cognito.show', $source))->assertOk()
        ->assertInertia(fn ($page) => $page->component('integrations/cognito-source')->where('source.tokenLast4', $source->token_last4)->where('urlTemplate.submit', url('/hooks/cognito/{token}/submit')));

    $old = $source->token_hash;
    $this->actingAs($admin)->post(route('integrations.cognito.rotate', $source))->assertRedirect();
    expect($source->fresh()?->token_hash)->not->toBe($old);
    postCognito($this, 'submit', $this->payload, 'x'.str_repeat('y', 47))->assertNotFound();

    $this->actingAs(userWithRoles('employee'))->get(route('integrations.cognito.index'))->assertForbidden();
});

it('lets admins view payloads (audited), pick the person, replay and discard', function (): void {
    $admin = userWithRoles('hr_admin');
    $admin->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
    $this->source->forceFill(['mapping' => [...$this->source->mapping, 'fields' => [...$this->source->mapping['fields'], ['from' => 'Money', 'to' => 'amount']]]])->save();
    postCognito($this, 'submit', [...$this->payload, 'Email' => 'nobody@example.com', 'Money' => ['amount_minor' => 900, 'currency' => 'SAR']]);
    $receipt = CognitoReceipt::query()->sole();

    $this->actingAs($admin)->get(route('integrations.cognito.index', ['status' => 'needs_person']))->assertOk()
        ->assertInertia(fn ($page) => $page->has('receipts.data', 1)->where('receipts.data.0.status', 'needs_person')->where('sources.0.waiting', 1));
    $this->actingAs($admin)->getJson(route('integrations.cognito.receipts.show', $receipt))->assertOk()->assertJsonPath('payload.Email', 'nobody@example.com');
    expect(Activity::query()->where('event', 'cognito.payload_viewed')->exists())->toBeTrue();

    $this->actingAs($admin)->post(route('integrations.cognito.receipts.assign', $receipt), ['employee' => $this->requester->ulid])->assertRedirect();
    expect($receipt->fresh()?->status)->toBe(ReceiptStatus::Processed);
    $this->actingAs($admin)->post(route('integrations.cognito.receipts.discard', $receipt))->assertSessionHasErrors();
});

it('opens Cognito-backed types on Cognito with the person prefilled (D60)', function (): void {
    $user = userWithRoles('employee');
    $this->requester->forceFill(['user_id' => $user->id])->save();

    $types = collect($this->actingAs($user)->get(route('requests.center'))->assertOk()->viewData('page')['props']['types']);
    $link = $types->firstWhere('code', 'EXPENSE')['externalUrl'] ?? null;

    expect($link)->toStartWith('https://www.cognitoforms.com/Najd/Expenses?entry=')
        ->and(json_decode(rawurldecode(Str::after((string) $link, 'entry=')), true))->toEqual(['Email' => 'rami@najd.example', 'Name' => ['First' => $this->requester->displayName('en')]]);

    $this->actingAs($user)->get(route('requests.create', $this->type))->assertRedirect();
});
