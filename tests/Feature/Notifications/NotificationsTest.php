<?php

declare(strict_types=1);

use App\Domain\Notifications\Mail\NotificationMail;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Notifications\Models\NotificationTemplate;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Actions\SubmitRequest;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Enums\Decision;
use App\Domain\Workflows\Enums\TaskStatus;
use App\Domain\Workflows\Events\TaskAssigned;
use App\Domain\Workflows\Models\ActionToken;
use App\Domain\Workflows\Models\WorkflowTask;
use App\Support\Actors\Actor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    Mail::fake();
    seedAccess();
    $branch = Branch::factory()->riyadh()->create();
    $this->managerUser = userWithRoles('employee');
    $manager = Employee::factory()->placedIn($branch)->create(['user_id' => $this->managerUser->id]);
    $this->requesterUser = userWithRoles('employee');
    Employee::factory()->placedIn($branch, null, $manager)->create(['user_id' => $this->requesterUser->id]);
    $this->type = tripRequestType();
});

function sendTrip(object $test): EmployeeRequest
{
    return resolve(SubmitRequest::class)->handle(Actor::user($test->requesterUser), $test->type, ['destination' => 'Jeddah']);
}

function approverTask(EmployeeRequest $request): WorkflowTask
{
    return WorkflowTask::query()->where('workflow_instance_id', $request->workflow_instance_id)->where('status', TaskStatus::Open->value)->sole();
}

/**
 * @return list<string>
 */
function deliveryStatuses(int $userId, string $channel): array
{
    return DB::table('notification_deliveries')->where('user_id', $userId)->where('channel', $channel)->orderBy('id')->pluck('status')->all();
}

it('tells the approver in-app and by email with one-tap links', function (): void {
    $request = sendTrip($this);

    $bell = $this->managerUser->notifications()->sole();
    expect($bell->data)->toMatchArray(['category' => 'approvals', 'title' => 'Approval needed: Business trip', 'url' => route('approvals.index', ['task' => approverTask($request)->ulid])])
        ->and(ActionToken::query()->count())->toBe(2);

    Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->hasTo($this->managerUser->email)
        && str_contains($mail->subjectLine, $request->number)
        && array_keys($mail->actions) === ['approved', 'rejected']);
});

it('writes to each person in their own language', function (): void {
    $this->managerUser->forceFill(['locale' => 'ar'])->save();
    sendTrip($this);

    expect($this->managerUser->notifications()->sole()->data['title'])->toBe('مطلوب موافقتك: رحلة عمل');
    Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->recipientLocale === 'ar');
});

it('tells the requester the outcome with the approver comment', function (): void {
    $request = sendTrip($this);
    resolve(WorkflowEngine::class)->decide(approverTask($request), Decision::Approved, $this->managerUser, 'Safe travels');

    expect($this->requesterUser->notifications()->sole()->data)->toMatchArray(['category' => 'requests', 'title' => 'Approved: your Business trip', 'url' => route('requests.show', $request)]);
    Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->hasTo($this->requesterUser->email) && str_contains($mail->bodyText, 'Safe travels'));
});

it('respects a switched-off channel but keeps critical in-app messages', function (): void {
    NotificationPreference::query()->upsert([
        ['user_id' => $this->requesterUser->id, 'category' => 'requests', 'channel' => 'email', 'enabled' => false],
        ['user_id' => $this->managerUser->id, 'category' => 'approvals', 'channel' => 'in_app', 'enabled' => false],
    ], ['user_id', 'category', 'channel'], ['enabled']);

    $request = sendTrip($this);
    resolve(WorkflowEngine::class)->decide(approverTask($request), Decision::Rejected, $this->managerUser, 'Dates clash');

    expect($this->managerUser->notifications()->count())->toBe(1)
        ->and(deliveryStatuses($this->requesterUser->id, 'email'))->toBe(['disabled'])
        ->and($this->requesterUser->notifications()->sole()->data['title'])->toBe('Not approved: your Business trip');
});

it('holds non-critical email until quiet hours end, but not approvals', function (): void {
    CarbonImmutable::setTestNow('2026-10-14 23:30:00');
    $this->requesterUser->forceFill(['timezone' => 'UTC', 'quiet_hours_start' => '22:00', 'quiet_hours_end' => '07:00'])->save();
    $this->managerUser->forceFill(['timezone' => 'UTC', 'quiet_hours_start' => '22:00', 'quiet_hours_end' => '07:00'])->save();

    $request = sendTrip($this);
    resolve(WorkflowEngine::class)->decide(approverTask($request), Decision::Approved, $this->managerUser);

    expect(deliveryStatuses($this->managerUser->id, 'email'))->toBe(['queued'])
        ->and(deliveryStatuses($this->requesterUser->id, 'email'))->toBe(['deferred']);
    Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->hasTo($this->requesterUser->email)
        && $mail->delay instanceof DateTimeInterface && CarbonImmutable::instance($mail->delay)->equalTo(CarbonImmutable::parse('2026-10-15 07:00:00', 'UTC')));
});

it('gathers non-critical email into the daily digest', function (): void {
    $this->requesterUser->forceFill(['digest_enabled' => true])->save();
    $request = sendTrip($this);
    resolve(WorkflowEngine::class)->decide(approverTask($request), Decision::Approved, $this->managerUser);

    expect(deliveryStatuses($this->requesterUser->id, 'email'))->toBe(['digest']);

    $this->artisan('notifications:digest')->assertSuccessful();
    $this->artisan('notifications:digest')->assertSuccessful();

    Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->hasTo($this->requesterUser->email) && count($mail->items) === 1);
    Mail::assertQueuedCount(2);
});

it('never repeats a message for the same cause', function (): void {
    $request = sendTrip($this);
    event(new TaskAssigned(approverTask($request)));

    expect($this->managerUser->notifications()->count())->toBe(1)
        ->and(ActionToken::query()->count())->toBe(2);
});

it('uses an admin template override, rendered safely', function (): void {
    NotificationTemplate::query()->create(['event' => 'approvals.task_assigned', 'channel' => 'in_app', 'locale' => 'en', 'subject' => 'Please review {{ request_number }} <b>now</b>', 'body' => '{{ requester }}']);
    $request = sendTrip($this);

    expect($this->managerUser->notifications()->sole()->data['title'])->toBe("Please review {$request->number} <b>now</b>");
});
