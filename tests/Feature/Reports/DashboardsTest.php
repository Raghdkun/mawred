<?php

declare(strict_types=1);

use App\Domain\Access\Models\User;
use App\Domain\Attendance\Models\AttendanceDay;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Leave\Models\LeaveRequest;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\DocumentType;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\EmployeeDocument;
use App\Domain\Policies\Models\LeaveType;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Phase 8c: role dashboards on the home page (D115, D123), each scoped to the viewer.
*/
beforeEach(function (): void {
    seedAccess();
    $this->travelTo(CarbonImmutable::parse('2026-10-20 07:00', 'UTC'));
    $this->alpha = Branch::factory()->riyadh()->create(['name' => ['en' => 'Alpha branch', 'ar' => 'فرع ألفا']]);
    $this->bravo = Branch::factory()->riyadh()->create(['name' => ['en' => 'Bravo branch', 'ar' => 'فرع برافو']]);
    $type = DocumentType::query()->create(['code' => 'ID', 'name' => ['en' => 'ID card', 'ar' => 'الهوية']]);
    $leave = LeaveType::query()->create(['code' => 'ANNUAL', 'name' => ['en' => 'Annual leave', 'ar' => 'سنوية'], 'unit' => 'days', 'is_paid' => true]);

    foreach (['Alpha' => $this->alpha, 'Bravo' => $this->bravo] as $label => $branch) {
        $late = Employee::factory()->placedIn($branch)->create(['name_en' => "{$label} Late"]);
        $away = Employee::factory()->placedIn($branch)->create(['name_en' => "{$label} Away"]);
        EmployeeDocument::query()->create(['employee_id' => $late->id, 'document_type_id' => $type->id, 'expires_on' => '2026-11-01']);
        AttendanceDay::query()->create(['employee_id' => $late->id, 'branch_id' => $branch->id, 'date' => '2026-10-20', 'schedule_source' => 'policy', 'status' => 'late', 'late_minutes' => 25, 'first_in' => '2026-10-20 05:25:00+00']);
        AttendanceDay::query()->create(['employee_id' => $away->id, 'branch_id' => $branch->id, 'date' => '2026-10-20', 'schedule_source' => 'policy', 'status' => 'on_leave']);
        LeaveRequest::query()->create(['employee_id' => $away->id, 'kind' => 'leave', 'leave_type_id' => $leave->id, 'starts_on' => '2026-10-19', 'ends_on' => '2026-10-22', 'amount' => 4, 'status' => 'approved']);
    }
});

function dashboardProp(object $test, User $user, string $prop): array
{
    $value = null;
    $test->actingAs($user)->get(route('dashboard'))
        ->assertInertia(function (Assert $page) use (&$value, $prop): Assert {
            return $page->component('home/index')->loadDeferredProps(function (Assert $reload) use (&$value, $prop): Assert {
                $value = $reload->toArray()['props'][$prop] ?? null;

                return $reload;
            });
        });

    return $value ?? [];
}

it('shows HR the KPIs, expiring documents, attendance today and integration health', function (): void {
    IntegrationConnection::query()->create(['type' => 'email', 'name' => 'default', 'status' => 'disconnected']);
    $hr = userWithRoles('hr_admin');
    $hr->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();

    $hr->forceFill(['locale' => 'en'])->save();
    $this->actingAs($hr)->get(route('dashboard'))->assertInertia(fn (Assert $page): Assert => $page->where('layout', 'hr_admin')->missing('hr')->where('team', null)->where('mine', null));
    $data = dashboardProp($this, $hr, 'hr');

    expect(collect($data['kpis'])->pluck('key')->all())->toBe(['headcount', 'turnover', 'openPositions', 'waitingRequests'])
        ->and($data['kpis'][0]['value'])->toBe(4)
        ->and($data['kpis'][0]['trend'])->toHaveCount(12)
        ->and($data['kpis'][0]['href'])->toContain('/reports/people.headcount')
        ->and($data['expiring']['total'])->toBe(2)
        ->and(collect($data['attendance'])->pluck('branch')->sort()->values()->all())->toBe(['Alpha branch', 'Bravo branch'])
        ->and($data['attendance'][0])->toMatchArray(['late' => 1, 'away' => 1])
        ->and($data['integrations'][0]['status'])->toBe('disconnected');
});

it('keeps an HR officer\'s dashboard to their branch', function (): void {
    $officer = userWithRoles('hr_officer');
    $officer->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
    Employee::factory()->placedIn($this->alpha)->create(['user_id' => $officer->id]);

    $data = dashboardProp($this, $officer, 'hr');

    expect($data['kpis'][0]['value'])->toBe(3)
        ->and($data['expiring']['total'])->toBe(1)
        ->and(collect($data['attendance'])->pluck('branch')->all())->toBe(['Alpha branch'])
        ->and($data['integrations'])->toBeNull()
        ->and(collect($data['kpis'])->pluck('key')->all())->not->toContain('openPositions');
});

it('shows a manager their team today and nobody else\'s', function (): void {
    $managerUser = userWithRoles('branch_manager');
    Employee::factory()->placedIn($this->alpha)->create(['user_id' => $managerUser->id]);

    $team = dashboardProp($this, $managerUser, 'team');

    expect($team['date'])->toBe('2026-10-20')
        ->and($team['today'])->toMatchArray(['in' => 1, 'late' => 1, 'away' => 1])
        ->and(collect($team['away'])->pluck('name')->all())->toBe(['Alpha Away'])
        ->and($team['away'][0]['until'])->toBe('2026-10-22')
        ->and(collect($team['late'])->pluck('name')->all())->toBe(['Alpha Late'])
        ->and($team['late'][0]['minutes'])->toBe(25)
        ->and(collect($team['kpis'])->firstWhere('key', 'team')['value'])->toBe(2)
        ->and($team['attendanceHref'])->toContain('/attendance/board');
});

it('gives everyone their check-in card and agenda', function (): void {
    $user = userWithRoles('employee');
    $me = Employee::factory()->placedIn($this->alpha)->create(['user_id' => $user->id]);
    AttendanceDay::query()->create(['employee_id' => $me->id, 'branch_id' => $this->alpha->id, 'date' => '2026-10-20', 'schedule_source' => 'policy', 'status' => 'present', 'first_in' => '2026-10-20 05:00:00+00']);
    LeaveRequest::query()->create(['employee_id' => $me->id, 'kind' => 'leave', 'leave_type_id' => LeaveType::query()->value('id'), 'starts_on' => '2026-10-22', 'ends_on' => '2026-10-23', 'amount' => 2, 'status' => 'approved']);

    $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page): Assert => $page->where('hr', null)->where('team', null));
    $mine = dashboardProp($this, $user, 'mine');

    expect($mine['clock'])->toMatchArray(['date' => '2026-10-20', 'timezone' => 'Asia/Riyadh', 'status' => 'present', 'next' => 'out'])
        ->and($mine['clock']['firstIn'])->not->toBeNull()
        ->and(collect($mine['agenda'])->firstWhere('kind', 'leave'))->toMatchArray(['date' => '2026-10-22', 'endsAt' => '2026-10-23']);
});
