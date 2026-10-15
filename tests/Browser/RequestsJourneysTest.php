<?php

declare(strict_types=1);

use App\Domain\Access\Models\User;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Actions\SeedRequestTypes;
use App\Domain\Requests\Actions\SubmitRequest;
use App\Domain\Requests\Enums\RequestStatus;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Requests\Models\RequestType;
use App\Domain\Workflows\Models\WorkflowDefinition;
use App\Support\Actors\Actor;
use Carbon\CarbonImmutable;

/*
| Phase 3b journeys (EN + AR, axe): request center → form → detail, the approvals inbox, the
| workflow builder and notification settings.
*/
beforeEach(function (): void {
    seedAccess();
    resolve(SeedRequestTypes::class)->handle();
    $branch = Branch::factory()->riyadh()->create(['name' => ['en' => 'Riyadh HQ', 'ar' => 'المقر الرئيسي']]);
    $person = function (string $role, ?Employee $manager = null, string $name = 'Person') use ($branch): User {
        $user = userWithRoles($role);
        $user->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
        Employee::factory()->placedIn($branch, null, $manager)->create(['user_id' => $user->id, 'name_en' => $name]);

        return $user;
    };

    $this->manager = $person('employee', null, 'Saud Manager');
    $this->hr = $person('hr_officer', null, 'Huda HR');
    $this->admin = $person('hr_admin', Employee::query()->where('user_id', $this->manager->id)->sole(), 'Amal Admin');
    $this->requester = $person('employee', Employee::query()->where('user_id', $this->manager->id)->sole(), 'Rami Requester');
});

function pendingTrip(object $test): EmployeeRequest
{
    return resolve(SubmitRequest::class)->handle(Actor::user($test->requester), RequestType::query()->where('code', 'BUSINESS_TRIP')->sole(), [
        'destination' => 'Jeddah',
        'dates' => ['from' => CarbonImmutable::now()->addWeek()->toDateString(), 'to' => CarbonImmutable::now()->addWeek()->addDays(2)->toDateString()],
        'purpose' => 'Client visit',
    ]);
}

it('renders the request screens accessibly in both languages', function (string $locale): void {
    $request = pendingTrip($this);
    $this->requester->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->requester);

    foreach (['/requests/new', '/requests/new/'.RequestType::query()->where('code', 'DAY_OFF')->value('ulid'), '/requests', "/requests/{$request->ulid}", '/approvals/delegations', '/notifications/preferences'] as $path) {
        visit($path)->wait(0.8)->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    }
})->with(['en', 'ar']);

it('renders the approver and admin screens accessibly in both languages', function (string $locale): void {
    pendingTrip($this);
    $this->manager->forceFill(['locale' => $locale])->save();
    $this->admin->forceFill(['locale' => $locale])->save();

    $this->actingAs($this->manager);
    visit('/approvals')->wait(0.8)->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();

    $this->actingAs($this->admin);
    foreach (['/workflows', '/workflows/stuck', '/workflows/'.WorkflowDefinition::query()->where('code', 'BUSINESS_TRIP')->value('ulid'), '/notifications/templates'] as $path) {
        visit($path)->wait(1)->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    }
})->with(['en', 'ar']);

it('submits a day off and approves it from the inbox', function (string $locale, array $text): void {
    foreach ([$this->requester, $this->manager, $this->hr] as $user) {
        $user->forceFill(['locale' => $locale])->save();
    }

    $this->actingAs($this->requester);
    visit('/requests/new')
        ->wait(0.8)
        ->click($text['type'])
        ->wait(0.8)
        ->fill('#field-date', CarbonImmutable::now()->next(CarbonImmutable::MONDAY)->toDateString())
        ->click($text['send'])
        ->wait(1.2)
        ->assertSee($text['sent'])
        ->assertNoJavaScriptErrors();

    $request = EmployeeRequest::query()->sole();

    foreach ([$this->manager, $this->hr] as $approver) {
        $this->actingAs($approver);
        visit('/approvals')
            ->wait(0.8)
            ->assertSee($request->number)
            ->click('button[data-decision="approved"]')
            ->wait(1)
            ->assertSee($text['done'])
            ->assertNoJavaScriptErrors();
    }

    expect($request->fresh()?->status)->toBe(RequestStatus::Approved);

    $this->actingAs($this->requester);
    visit("/requests/{$request->ulid}")->wait(0.8)->assertSee($text['approved']);
})->with([
    'en' => ['en', ['type' => 'Day off or hourly permission', 'send' => 'Send request', 'sent' => 'Request sent', 'done' => 'You’re all caught up', 'approved' => 'Approved']],
    'ar' => ['ar', ['type' => 'إجازة يوم أو استئذان بالساعات', 'send' => 'أرسل الطلب', 'sent' => 'أُرسل الطلب', 'done' => 'لا شيء بانتظارك', 'approved' => 'مقبول']],
]);

it('test-runs a workflow in the builder', function (): void {
    $this->actingAs($this->admin);

    visit('/workflows/'.WorkflowDefinition::query()->where('code', 'ASSET_REQUEST')->value('ulid'))
        ->on()->macbook14()
        ->wait(1)
        ->click('Test run')
        ->click('Run')
        ->wait(1.5)
        ->assertSee('Outcome: Approved')
        ->assertNoJavaScriptErrors();
});

/*
 * Visual check (handoff, D32): 390 / 834 / 1440 × LTR / RTL; dark for the inbox and builder (★).
 */
it('captures the request screens for review', function (string $locale): void {
    $request = pendingTrip($this);
    foreach ([$this->requester, $this->manager, $this->admin] as $user) {
        $user->forceFill(['locale' => $locale])->save();
    }

    $shots = [
        [$this->requester, 'center', '/requests/new'],
        [$this->requester, 'form', '/requests/new/'.RequestType::query()->where('code', 'DAY_OFF')->value('ulid')],
        [$this->requester, 'mine', '/requests'],
        [$this->requester, 'detail', "/requests/{$request->ulid}"],
        [$this->requester, 'preferences', '/notifications/preferences'],
        [$this->manager, 'inbox', '/approvals'],
        [$this->admin, 'workflows', '/workflows'],
        [$this->admin, 'templates', '/notifications/templates'],
    ];

    foreach ($shots as [$user, $name, $path]) {
        $this->actingAs($user);
        visit($path)->on()->mobile()->wait(0.8)->screenshot(true, "requests-{$name}-{$locale}-390");
        visit($path)->on()->iPadPro()->wait(0.8)->screenshot(true, "requests-{$name}-{$locale}-834");
        visit($path)->on()->macbook14()->wait(0.8)->screenshot(true, "requests-{$name}-{$locale}-1440");
    }

    $this->actingAs($this->manager);
    visit('/approvals')->on()->macbook14()->inDarkMode()->wait(0.8)->screenshot(true, "requests-inbox-{$locale}-dark");
    $this->actingAs($this->admin);
    $builder = '/workflows/'.WorkflowDefinition::query()->where('code', 'SALARY_ADVANCE')->value('ulid');
    visit($builder)->on()->macbook14()->wait(1)->screenshot(true, "workflows-builder-{$locale}-1440");
    visit($builder)->on()->macbook14()->inDarkMode()->wait(1)->screenshot(true, "workflows-builder-{$locale}-dark");
})->with(['en', 'ar'])->skip(fn (): bool => ! env('SCREENSHOTS'), 'Set SCREENSHOTS=1 to capture.');
