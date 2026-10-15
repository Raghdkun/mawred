<?php

declare(strict_types=1);

use App\Domain\Access\Models\User;
use App\Domain\Leave\Actions\UpdateLeaveBalances;
use App\Domain\Leave\Enums\LeaveRequestStatus;
use App\Domain\Leave\Models\LeaveBlackout;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Actions\SeedPolicyDefaults;
use App\Domain\Requests\Actions\SeedRequestTypes;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Requests\Models\RequestType;
use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Enums\Decision;
use App\Domain\Workflows\Enums\TaskStatus;
use App\Domain\Workflows\Models\WorkflowTask;
use Carbon\CarbonImmutable;

/*
| Phase 5a journeys (EN + AR, axe): my leave, the request form's balance preview, the leave
| calendar, the profile Leave tab and blackout periods. Riyadh weekend is Friday + Saturday.
*/
beforeEach(function (): void {
    seedAccess();
    resolve(SeedPolicyDefaults::class)->handle();
    resolve(SeedRequestTypes::class)->handle();
    $branch = Branch::factory()->riyadh()->create(['name' => ['en' => 'Riyadh HQ', 'ar' => 'المقر الرئيسي']]);
    $person = function (string $role, ?Employee $manager = null, string $name = 'Person') use ($branch): User {
        $user = userWithRoles($role);
        $user->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
        $employee = Employee::factory()->placedIn($branch, null, $manager)->create(['user_id' => $user->id, 'name_en' => $name, 'hire_date' => '2024-01-01']);
        resolve(UpdateLeaveBalances::class)->handle($employee);

        return $user;
    };

    $this->manager = $person('employee', null, 'Saud Manager');
    $this->hr = $person('hr_officer', null, 'Huda HR');
    $this->admin = $person('hr_admin', null, 'Amal Admin');
    $this->requester = $person('employee', Employee::query()->where('user_id', $this->manager->id)->sole(), 'Rami Requester');
    // Sunday to Tuesday after next: three working days, clear of the weekend.
    $this->from = CarbonImmutable::now()->next(CarbonImmutable::SUNDAY)->addWeek();
});

function approveLeaveSteps(EmployeeRequest $request, User ...$approvers): void
{
    foreach ($approvers as $approver) {
        $task = WorkflowTask::query()->where('workflow_instance_id', $request->workflow_instance_id)->where('status', TaskStatus::Open->value)->sole();
        resolve(WorkflowEngine::class)->decide($task, Decision::Approved, $approver);
    }
}

it('requests annual leave with a live balance preview, then shows it once approved', function (string $locale, array $text): void {
    foreach ([$this->requester, $this->manager, $this->hr] as $user) {
        $user->forceFill(['locale' => $locale])->save();
    }

    $this->actingAs($this->requester);
    visit('/requests/new/'.RequestType::query()->where('code', 'ANNUAL_LEAVE')->value('ulid'))
        ->wait(0.8)
        ->assertSee($text['pick'])
        ->fill('#field-dates-from', $this->from->toDateString())
        ->fill('#field-dates-to', $this->from->addDays(2)->toDateString())
        ->wait(1.2)
        ->assertSeeIn('[data-quote-amount]', $text['amount'])
        ->assertNoAccessibilityIssues()
        ->click($text['send'])
        ->wait(1.2)
        ->assertSee($text['sent'])
        ->assertPresent('[data-leave-summary]')
        ->assertNoJavaScriptErrors();

    $request = EmployeeRequest::query()->sole();
    expect(LeaveRequest::query()->sole()->status)->toBe(LeaveRequestStatus::Pending);

    approveLeaveSteps($request, $this->manager, $this->hr);

    visit('/leave')
        ->wait(1)
        ->assertPresent('[data-leave-type="ANNUAL"]')
        ->assertSee($text['approved'])
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->with([
    'en' => ['en', ['pick' => 'Choose dates to see how much this takes.', 'amount' => '3 days', 'send' => 'Send request', 'sent' => 'Request sent', 'approved' => 'Approved']],
    'ar' => ['ar', ['pick' => 'اختر التواريخ لترى ما يستهلكه الطلب.', 'amount' => '3 أيام', 'send' => 'أرسل الطلب', 'sent' => 'أُرسل الطلب', 'approved' => 'معتمد']],
]);

it('renders the leave screens accessibly in both languages', function (string $locale): void {
    foreach ([$this->requester, $this->hr, $this->admin] as $user) {
        $user->forceFill(['locale' => $locale])->save();
    }

    LeaveBlackout::query()->create(['name' => ['en' => 'Stock count', 'ar' => 'الجرد'], 'starts_on' => $this->from->toDateString(), 'ends_on' => $this->from->addDays(3)->toDateString()]);
    $requester = Employee::query()->where('user_id', $this->requester->id)->sole();

    $this->actingAs($this->requester);
    foreach (['/leave', '/leave/calendar', '/dashboard'] as $path) {
        visit($path)->wait(1)->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    }

    $this->actingAs($this->hr);
    visit("/people/{$requester->ulid}?tab=leave")->wait(1)->assertPresent('[data-leave-type="ANNUAL"]')->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();

    $this->actingAs($this->admin);
    visit('/leave/blackouts')->wait(0.8)->assertPresent('[data-blackout]')->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
})->with(['en', 'ar']);

it('shows a phone agenda instead of the month grid', function (): void {
    $this->actingAs($this->requester);

    visit('/leave/calendar')->on()->mobile()->wait(1)
        ->assertNotPresent('[data-leave-grid]:not(.hidden)')
        ->assertScript('document.documentElement.scrollWidth <= document.documentElement.clientWidth', true)
        ->assertNoJavaScriptErrors();
});

/*
 * Visual check (handoff, D32): 390 / 834 / 1440 × LTR / RTL; dark for home (★).
 */
it('captures the leave screens for review', function (string $locale): void {
    foreach ([$this->requester, $this->hr] as $user) {
        $user->forceFill(['locale' => $locale])->save();
    }

    $requester = Employee::query()->where('user_id', $this->requester->id)->sole();
    $shots = [
        [$this->requester, 'mine', '/leave'],
        [$this->requester, 'form', '/requests/new/'.RequestType::query()->where('code', 'ANNUAL_LEAVE')->value('ulid')],
        [$this->requester, 'calendar', '/leave/calendar'],
        [$this->hr, 'profile', "/people/{$requester->ulid}?tab=leave"],
    ];

    foreach ($shots as [$user, $name, $path]) {
        $this->actingAs($user);
        visit($path)->on()->mobile()->wait(1)->screenshot(true, "leave-{$name}-{$locale}-390");
        visit($path)->on()->iPadPro()->wait(1)->screenshot(true, "leave-{$name}-{$locale}-834");
        visit($path)->on()->macbook14()->wait(1)->screenshot(true, "leave-{$name}-{$locale}-1440");
    }

    $this->actingAs($this->requester);
    visit('/dashboard')->on()->macbook14()->inDarkMode()->wait(1)->screenshot(true, "leave-home-{$locale}-dark");
})->with(['en', 'ar'])->skip(fn (): bool => ! env('SCREENSHOTS'), 'Set SCREENSHOTS=1 to capture.');
