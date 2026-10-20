<?php

declare(strict_types=1);

use App\Domain\Attendance\Models\AttendanceDay;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\DocumentType;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\EmployeeDocument;
use App\Domain\Policies\Models\LeaveType;
use Carbon\CarbonImmutable;

/*
| Phase 8c journeys (EN + AR, axe): the HR admin, manager and employee homes (handoff 4–6).
*/
beforeEach(function (): void {
    seedAccess();
    $this->branch = Branch::factory()->riyadh()->create(['name' => ['en' => 'Riyadh HQ', 'ar' => 'المقر الرئيسي']]);
    $other = Branch::factory()->riyadh()->create(['name' => ['en' => 'Jeddah', 'ar' => 'جدة']]);
    $today = CarbonImmutable::now('Asia/Riyadh')->toDateString();
    $type = DocumentType::query()->create(['code' => 'ID', 'name' => ['en' => 'Residency permit', 'ar' => 'الإقامة']]);
    $leave = LeaveType::query()->create(['code' => 'ANNUAL', 'name' => ['en' => 'Annual leave', 'ar' => 'سنوية'], 'unit' => 'days', 'is_paid' => true]);
    IntegrationConnection::query()->create(['type' => 'email', 'name' => 'Email', 'status' => 'healthy', 'health_checked_at' => now()]);
    IntegrationConnection::query()->create(['type' => 'whatsapp', 'name' => 'WhatsApp', 'status' => 'disconnected', 'health_checked_at' => now()]);

    foreach ([[$this->branch, ['present', 'present', 'present', 'late', 'on_leave', 'scheduled']], [$other, ['present', 'late', 'scheduled']]] as [$branch, $statuses]) {
        foreach ($statuses as $i => $status) {
            $person = Employee::factory()->placedIn($branch)->create(['name_en' => "Person {$i}", 'name_ar' => "موظف {$i}", 'hire_date' => CarbonImmutable::now()->subMonths($i * 2)->toDateString()]);
            AttendanceDay::query()->create(['employee_id' => $person->id, 'branch_id' => $branch->id, 'date' => $today, 'schedule_source' => 'policy', 'status' => $status, 'late_minutes' => $status === 'late' ? 18 : 0, 'first_in' => $status === 'scheduled' || $status === 'on_leave' ? null : now()->subHours(2)]);

            if ($status === 'on_leave') {
                LeaveRequest::query()->create(['employee_id' => $person->id, 'kind' => 'leave', 'leave_type_id' => $leave->id, 'starts_on' => $today, 'ends_on' => CarbonImmutable::parse($today)->addDays(2)->toDateString(), 'amount' => 3, 'status' => 'approved']);
            }

            if ($i < 2) {
                EmployeeDocument::query()->create(['employee_id' => $person->id, 'document_type_id' => $type->id, 'expires_on' => CarbonImmutable::parse($today)->addDays($i === 0 ? -3 : 12)->toDateString()]);
            }
        }
    }

    $this->hr = userWithRoles('hr_admin');
    $this->hr->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
    $this->managerUser = userWithRoles('branch_manager');
    Employee::factory()->placedIn($this->branch)->create(['user_id' => $this->managerUser->id, 'name_en' => 'Maha Manager']);
    $this->employeeUser = userWithRoles('employee');
    $me = Employee::factory()->placedIn($this->branch)->create(['user_id' => $this->employeeUser->id, 'name_en' => 'Sara Staff']);
    AttendanceDay::query()->create(['employee_id' => $me->id, 'branch_id' => $this->branch->id, 'date' => $today, 'schedule_source' => 'policy', 'status' => 'present', 'first_in' => now()->subHour()]);
    LeaveRequest::query()->create(['employee_id' => $me->id, 'kind' => 'leave', 'leave_type_id' => $leave->id, 'starts_on' => CarbonImmutable::parse($today)->addDays(3)->toDateString(), 'ends_on' => CarbonImmutable::parse($today)->addDays(4)->toDateString(), 'amount' => 2, 'status' => 'approved']);
});

it('shows HR their dashboard', function (string $locale): void {
    $this->hr->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->hr);

    visit('/dashboard')->on()->macbook14()->wait(2)
        ->assertPresent('[data-hr-dashboard]')
        ->assertPresent('[data-integration-banner]')
        ->assertPresent('[data-kpi="headcount"]')
        ->assertPresent('[data-attendance-today]')
        ->assertPresent('[data-expiring-documents]')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    visit('/dashboard')->on()->mobile()->wait(2)
        ->assertScript('document.documentElement.scrollWidth <= document.documentElement.clientWidth', true)
        ->assertNoAccessibilityIssues();
})->with(['en', 'ar']);

it('shows a manager their team today', function (string $locale): void {
    $this->managerUser->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->managerUser);

    visit('/dashboard')->on()->macbook14()->wait(2)
        ->assertPresent('[data-team-today]')
        ->assertPresent('[data-team-away]')
        ->assertPresent('[data-team-late]')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->with(['en', 'ar']);

it('shows an employee their check-in card and agenda', function (string $locale): void {
    $this->employeeUser->forceFill(['locale' => $locale])->save();
    $this->actingAs($this->employeeUser);

    visit('/dashboard')->on()->mobile()->wait(2)
        ->assertPresent('[data-clock-card]')
        ->assertPresent('[data-agenda-item="leave"]')
        ->assertScript('document.documentElement.scrollWidth <= document.documentElement.clientWidth', true)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
})->with(['en', 'ar']);

/**
 * Screens for the visual check (handoff 4–6; 4 and 6 are ★, so dark too), written to
 * tests/Browser/Screenshots; run with SCREENSHOTS=1.
 */
it('captures the home dashboards', function (string $locale): void {
    foreach (['hr' => $this->hr, 'manager' => $this->managerUser, 'employee' => $this->employeeUser] as $name => $user) {
        $user->forceFill(['locale' => $locale])->save();
        $this->actingAs($user);
        visit('/dashboard')->on()->mobile()->wait(2)->screenshot(true, "dash8c-{$name}-{$locale}-390");
        visit('/dashboard')->on()->iPadPro()->wait(2)->screenshot(true, "dash8c-{$name}-{$locale}-834");
        visit('/dashboard')->on()->macbook14()->wait(2)->screenshot(true, "dash8c-{$name}-{$locale}-1440");

        if ($name !== 'manager') {
            visit('/dashboard')->on()->macbook14()->inDarkMode()->wait(2)->screenshot(true, "dash8c-{$name}-{$locale}-dark");
        }
    }
})->with(['en', 'ar'])->skip(fn (): bool => ! env('SCREENSHOTS'), 'Set SCREENSHOTS=1 to capture.');
