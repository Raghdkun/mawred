<?php

declare(strict_types=1);

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\EmploymentType;
use App\Domain\Organization\Models\Grade;
use App\Domain\People\Actions\ChangePlacement;
use App\Domain\People\Actions\HireEmployee;
use App\Domain\People\Data\HireEmployeeData;
use App\Domain\People\Data\PlacementInputData;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\EmployeeAssignment;
use App\Domain\Policies\Actions\AssignPolicySet;
use App\Domain\Policies\Actions\EndPolicyAssignment;
use App\Domain\Policies\Actions\ExportPolicyPreset;
use App\Domain\Policies\Actions\ImportPolicyPreset;
use App\Domain\Policies\Actions\SavePolicySet;
use App\Domain\Policies\Actions\SeedPolicyDefaults;
use App\Domain\Policies\Actions\SetPolicyValues;
use App\Domain\Policies\Data\ChainLinkData;
use App\Domain\Policies\Data\ResolvedValueData;
use App\Domain\Policies\Data\Values\GraceValue;
use App\Domain\Policies\Enums\PolicyLevel;
use App\Domain\Policies\Models\PolicySet;
use App\Domain\Policies\Models\PolicyValue;
use App\Domain\Policies\Support\PolicyResolver;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

beforeEach(function (): void {
    $this->travelTo('2026-10-15 09:00:00');
    resolve(SeedPolicyDefaults::class)->handle();
    $this->actor = Actor::system('test');
    $this->branch = Branch::factory()->riyadh()->create();
    $this->ops = Department::factory()->create();
    $this->field = Department::factory()->childOf($this->ops)->create();
    $this->grade = Grade::factory()->create();
    $this->type = EmploymentType::factory()->create();
    $this->employee = Employee::factory()->placedIn($this->branch, $this->field)->create(['hire_date' => '2020-01-01']);
    EmployeeAssignment::query()->where('employee_id', $this->employee->id)->update(['grade_id' => $this->grade->id, 'employment_type_id' => $this->type->id]);
});

function policySet(string $name, array $values, string $from = '2026-01-01'): PolicySet
{
    $set = resolve(SavePolicySet::class)->handle(Actor::system('test'), ['en' => $name]);
    resolve(SetPolicyValues::class)->handle(Actor::system('test'), $set, $values, $from, 'test');

    return $set;
}

function grace(object $test, string $date = '2026-10-15'): ResolvedValueData
{
    app()->forgetScopedInstances();

    return resolve(PolicyResolver::class)->get($test->employee->fresh(), $date, 'attendance.grace');
}

it('falls back to the neutral default, then the branch for the work week', function (): void {
    expect(grace($this))->level->toBe('global')->value->toBe(['lateMinutes' => 15, 'earlyLeaveMinutes' => 0]);

    $weekend = resolve(PolicyResolver::class)->get($this->employee, '2026-10-15', 'work.weekend');
    expect($weekend->level)->toBe('branch_default')->and($weekend->value['days'])->toBe([5, 6]);
});

it('lets the most specific level win, employment type above grade, nearest department first', function (): void {
    $assign = resolve(AssignPolicySet::class);
    $assign->handle($this->actor, policySet('Branch', ['attendance.grace' => ['lateMinutes' => 10]]), PolicyLevel::Branch, $this->branch->id, '2026-01-01', 'test');
    expect(grace($this))->level->toBe('branch')->value->lateMinutes->toBe(10);

    $assign->handle($this->actor, policySet('Ops', ['attendance.grace' => ['lateMinutes' => 9]]), PolicyLevel::Department, $this->ops->id, '2026-01-01', 'test');
    expect(grace($this)->value['lateMinutes'])->toBe(9);

    $assign->handle($this->actor, policySet('Field', ['attendance.grace' => ['lateMinutes' => 8]]), PolicyLevel::Department, $this->field->id, '2026-01-01', 'test');
    expect(grace($this)->value['lateMinutes'])->toBe(8);

    $assign->handle($this->actor, policySet('Type', ['attendance.grace' => ['lateMinutes' => 6]]), PolicyLevel::EmploymentType, $this->type->id, '2026-01-01', 'test');
    $assign->handle($this->actor, policySet('Grade', ['attendance.grace' => ['lateMinutes' => 7]]), PolicyLevel::Grade, $this->grade->id, '2026-01-01', 'test');

    $resolved = grace($this);
    expect($resolved->level)->toBe('employment_type')->and($resolved->value['lateMinutes'])->toBe(6)
        ->and(array_map(fn (ChainLinkData $link): string => $link->level, $resolved->chain))->toBe(['employment_type', 'grade', 'department', 'department', 'branch', 'global', 'default']);

    $assign->handle($this->actor, policySet('Sara only', ['attendance.grace' => ['lateMinutes' => 0]]), PolicyLevel::Employee, $this->employee->id, '2026-01-01', 'test');
    expect(grace($this)->value['lateMinutes'])->toBe(0);
});

it('skips sets that do not define the key (partial sets)', function (): void {
    resolve(AssignPolicySet::class)->handle($this->actor, policySet('Overtime only', ['overtime.rules' => ['weekday' => 1.25]]), PolicyLevel::Branch, $this->branch->id, '2026-01-01', 'test');

    expect(grace($this)->level)->toBe('global');
    expect(resolve(PolicyResolver::class)->get($this->employee, '2026-10-15', 'overtime.rules')->value['weekday'])->toBe(1.25);
});

it('keeps past values when a value changes from a date', function (): void {
    $set = policySet('Branch', ['attendance.grace' => ['lateMinutes' => 10]], '2026-01-01');
    resolve(AssignPolicySet::class)->handle($this->actor, $set, PolicyLevel::Branch, $this->branch->id, '2026-01-01', 'test');

    resolve(SetPolicyValues::class)->handle($this->actor, $set, ['attendance.grace' => ['lateMinutes' => 20]], '2026-11-01');

    expect(grace($this, '2026-10-31')->value['lateMinutes'])->toBe(10)
        ->and(grace($this, '2026-11-01')->value['lateMinutes'])->toBe(20)
        ->and(PolicyValue::query()->where('policy_set_id', $set->id)->orderBy('effective_from')->pluck('effective_to')->map(fn ($d) => $d?->toDateString())->all())->toBe(['2026-10-31', null]);

    resolve(SetPolicyValues::class)->handle($this->actor, $set, ['attendance.grace' => null], '2026-12-01');
    expect(grace($this, '2026-12-01')->level)->toBe('global');
});

it('requires a reason to back-date and refuses invalid or unknown values', function (): void {
    $set = resolve(SavePolicySet::class)->handle($this->actor, ['en' => 'X']);

    expect(fn () => resolve(SetPolicyValues::class)->handle($this->actor, $set, ['attendance.grace' => ['lateMinutes' => 5]], '2026-01-01'))
        ->toThrow(DomainRuleViolation::class, 'reason')
        ->and(fn () => resolve(SetPolicyValues::class)->handle($this->actor, $set, ['attendance.grace' => ['lateMinutes' => 999]], '2026-11-01'))
        ->toThrow(fn (DomainRuleViolation $e) => expect($e->details)->toHaveKey('values.attendance.grace.lateMinutes'))
        ->and(fn () => resolve(SetPolicyValues::class)->handle($this->actor, $set, ['nope.key' => []], '2026-11-01'))
        ->toThrow(DomainRuleViolation::class);
});

it('ends assignments so less specific levels take over', function (): void {
    $assignment = resolve(AssignPolicySet::class)->handle($this->actor, policySet('Branch', ['attendance.grace' => ['lateMinutes' => 10]]), PolicyLevel::Branch, $this->branch->id, '2026-01-01', 'test');
    resolve(EndPolicyAssignment::class)->handle($assignment, '2026-10-31');

    expect(grace($this, '2026-10-31')->level)->toBe('branch')->and(grace($this, '2026-11-01')->level)->toBe('global');
});

it('follows the employee placement on the date being resolved', function (): void {
    $dubai = Branch::factory()->create();
    resolve(AssignPolicySet::class)->handle($this->actor, policySet('Dubai', ['attendance.grace' => ['lateMinutes' => 5]]), PolicyLevel::Branch, $dubai->id, '2026-01-01', 'test');
    resolve(ChangePlacement::class)->handle($this->actor, $this->employee, new PlacementInputData($dubai->ulid), '2026-12-01');

    expect(grace($this, '2026-11-30')->value['lateMinutes'])->toBe(15)
        ->and(grace($this, '2026-12-01')->value['lateMinutes'])->toBe(5);

    expect(resolve(PolicyResolver::class)->value($this->employee, '2026-12-01', 'attendance.grace'))->toBeInstanceOf(GraceValue::class);
});

it('round-trips a preset and seeds defaults only once', function (): void {
    $neutral = PolicySet::query()->where('preset_key', 'neutral-default')->sole();
    $export = resolve(ExportPolicyPreset::class)->handle($neutral, '2026-10-15');

    expect($export['format'])->toBe('mawred.policy-preset/v1')
        ->and($export['values'])->toHaveKeys(['leave.type.ANNUAL', 'overtime.rules', 'employment.probation'])
        ->and($export['values'])->not->toHaveKey('work.weekend')
        ->and(collect($export['leaveTypes'])->pluck('code'))->toContain('MATERNITY');

    $copy = resolve(ImportPolicyPreset::class)->handle($this->actor, [...$export, 'key' => 'copy'], '2026-11-01');
    expect($copy->values()->count())->toBe(count($export['values']));

    resolve(SeedPolicyDefaults::class)->handle();
    expect(PolicySet::query()->where('preset_key', 'neutral-default')->count())->toBe(1);
});

it('sets a default probation end from policy at hire', function (): void {
    $hired = resolve(HireEmployee::class)->handle($this->actor, HireEmployeeData::from([
        'nameEn' => 'New Person', 'hireDate' => '2026-11-01', 'placement' => ['branchId' => $this->branch->ulid],
    ]));

    expect($hired->probation_ends_on?->toDateString())->toBe('2027-01-29');
});
