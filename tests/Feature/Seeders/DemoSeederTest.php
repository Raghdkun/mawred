<?php

declare(strict_types=1);

use App\Domain\Access\Models\User;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Enums\EmployeeStatus;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Models\HolidayCalendar;
use App\Domain\Policies\Support\PolicyResolver;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Support\Facades\Storage;

it('builds the demo company through the real actions, once', function (): void {
    Storage::fake('local');
    config(['seeding.demo_password' => 'local-demo-password-123']);

    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class); // idempotent

    expect(Branch::query()->pluck('weekend_days', 'code')->all())->toBe(['RUH-HQ' => [5, 6], 'DXB' => [6, 7], 'NYC' => [6, 7]])
        ->and(Employee::query()->count())->toBe(115)
        ->and(Employee::query()->whereNull('current_manager_id')->count())->toBe(1)
        ->and(Employee::query()->where('status', EmployeeStatus::Pending)->count())->toBe(1)
        ->and(Employee::query()->where('status', EmployeeStatus::Terminated)->count())->toBe(1)
        ->and(Employee::query()->whereNotNull('name_ar')->count())->toBeGreaterThan(90);

    $riyadhEmployee = Employee::query()->where('current_branch_id', Branch::query()->where('code', 'RUH-HQ')->value('id'))->firstOrFail();
    expect(resolve(PolicyResolver::class)->get($riyadhEmployee, '2026-10-15', 'attendance.grace')->level)->toBe('branch')
        ->and(HolidayCalendar::query()->pluck('code')->sort()->values()->all())->toBe(['SA_DEMO', 'US_FEDERAL']);

    $manager = User::query()->where('email', 'branch.manager@mawred.test')->sole();
    expect($manager->hasRole('branch_manager'))->toBeTrue()
        ->and(Employee::query()->where('user_id', $manager->id)->sole()->current_branch_id)->toBe(Branch::query()->where('code', 'DXB')->value('id'));
});

it('refuses to run without a demo password', function (): void {
    config(['seeding.demo_password' => null]);

    expect(fn () => $this->seed(DemoSeeder::class))->toThrow(RuntimeException::class, 'DEMO_PASSWORD');
});
