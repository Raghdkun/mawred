<?php

declare(strict_types=1);

use App\Domain\Policies\Actions\DeleteLeaveType;
use App\Domain\Policies\Actions\SaveLeaveType;
use App\Domain\Policies\Actions\SeedPolicyDefaults;
use App\Domain\Policies\Data\LeaveTypeInputData;
use App\Domain\Policies\Models\LeaveType;
use App\Domain\Policies\Support\PolicyKeys;
use App\Support\Errors\DomainRuleViolation;

beforeEach(function (): void {
    resolve(SeedPolicyDefaults::class)->handle();
});

function leaveInput(array $overrides = []): LeaveTypeInputData
{
    return LeaveTypeInputData::from([
        'code' => 'hajj', 'name' => ['en' => 'Hajj leave', 'ar' => 'إجازة الحج'], 'unit' => 'days', 'isPaid' => true,
        'allowsHalfDays' => false, 'eligibleGender' => null, 'requiresAttachment' => true, 'sortOrder' => 80, 'isActive' => true,
        ...$overrides,
    ]);
}

it('adds a leave type and its rule key to the registry', function (): void {
    $type = resolve(SaveLeaveType::class)->handle(leaveInput());

    app()->forgetScopedInstances();
    expect($type->code)->toBe('HAJJ')
        ->and(resolve(PolicyKeys::class)->find('leave.type.HAJJ'))->not->toBeNull();
});

it('keeps the code fixed on edit and drops half days for hourly types', function (): void {
    $type = resolve(SaveLeaveType::class)->handle(leaveInput());
    resolve(SaveLeaveType::class)->handle(leaveInput(['code' => 'OTHER', 'unit' => 'hours', 'allowsHalfDays' => true]), $type);

    expect($type->fresh())->code->toBe('HAJJ')->allows_half_days->toBeFalse();
});

it('archives a type, removes its key and never reuses the code', function (): void {
    $type = resolve(SaveLeaveType::class)->handle(leaveInput());
    resolve(DeleteLeaveType::class)->handle($type);

    app()->forgetScopedInstances();
    expect(resolve(PolicyKeys::class)->find('leave.type.HAJJ'))->toBeNull()
        ->and(LeaveType::withTrashed()->where('code', 'HAJJ')->exists())->toBeTrue()
        ->and(fn () => resolve(SaveLeaveType::class)->handle(leaveInput()))->toThrow(DomainRuleViolation::class);
});
