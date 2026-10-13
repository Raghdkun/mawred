<?php

declare(strict_types=1);

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Position;
use App\Domain\People\Actions\AddSecondaryAssignment;
use App\Domain\People\Actions\ChangePlacement;
use App\Domain\People\Actions\HireEmployee;
use App\Domain\People\Actions\SyncEmployeePlacement;
use App\Domain\People\Actions\TerminateEmployee;
use App\Domain\People\Data\HireEmployeeData;
use App\Domain\People\Data\PlacementInputData;
use App\Domain\People\Enums\EmployeeStatus;
use App\Domain\People\Models\Employee;
use App\Domain\People\Models\EmployeeAssignment;
use App\Domain\Setup\Support\Settings;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function hireInto(Branch $branch, array $overrides = [], ?Employee $manager = null, ?Department $department = null): Employee
{
    return resolve(HireEmployee::class)->handle(Actor::system('test'), HireEmployeeData::from([
        'nameEn' => 'Sara Haddad',
        'nameAr' => 'سارة حداد',
        'hireDate' => '2025-03-01',
        'placement' => ['branchId' => $branch->ulid, 'managerId' => $manager?->ulid, 'departmentId' => $department?->ulid],
        ...$overrides,
    ]));
}

function paths(): array
{
    return DB::table('employee_reporting_paths')->orderBy('ancestor_id')->orderBy('descendant_id')
        ->get()->map(fn (object $row): string => "{$row->ancestor_id}>{$row->descendant_id}@{$row->depth}")->all();
}

it('hires with an auto number, normalised contact details and a current placement', function (): void {
    resolve(Settings::class)->set('people.number_prefix', 'RYD-');
    $branch = Branch::factory()->riyadh()->create();

    $employee = hireInto($branch, ['workEmail' => 'Sara@Example.COM', 'workPhone' => '050 123 4567']);

    expect($employee)
        ->employee_number->toMatch('/^RYD-\d{6}$/')
        ->work_email->toBe('sara@example.com')
        ->work_phone_e164->toBe('+966501234567')
        ->status->toBe(EmployeeStatus::Active)
        ->current_branch_id->toBe($branch->id)
        ->and($employee->assignments()->sole()->is_current)->toBeTrue()
        ->and($employee->search_text)->toContain('ساره حداد')
        ->and($employee->displayName('ar'))->toBe('سارة حداد');

    expect(fn (): Employee => hireInto($branch, ['workEmail' => 'sara@example.com']))->toThrow(DomainRuleViolation::class)
        ->and(fn (): Employee => hireInto($branch, ['employeeNumber' => $employee->employee_number]))->toThrow(DomainRuleViolation::class)
        ->and(fn (): Employee => hireInto($branch, ['workPhone' => '123']))->toThrow(DomainRuleViolation::class);
});

it('keeps a future hire pending but visible to their branch, then activates on the day', function (): void {
    $this->travelTo('2026-10-01 12:00:00');
    $branch = Branch::factory()->create();

    $employee = hireInto($branch, ['hireDate' => '2026-10-15']);

    expect($employee->status)->toBe(EmployeeStatus::Pending)
        ->and($employee->current_branch_id)->toBe($branch->id)
        ->and($employee->assignments()->sole()->is_current)->toBeFalse();

    $this->travelTo('2026-10-15 16:00:00');
    $this->artisan('people:sync-placements')->assertSuccessful();

    expect($employee->fresh())->status->toBe(EmployeeStatus::Active)
        ->and($employee->assignments()->sole()->is_current)->toBeTrue();
});

it('uses the branch-local date to decide what is current', function (): void {
    // 22:30 UTC on the 14th is already the 15th in Riyadh (UTC+3).
    $this->travelTo('2026-10-14 22:30:00');
    $employee = hireInto(Branch::factory()->riyadh()->create(), ['hireDate' => '2026-10-15']);

    expect($employee->status)->toBe(EmployeeStatus::Active);
});

it('transfers with effective dating and keeps one primary at a time', function (): void {
    $this->travelTo('2026-10-01 09:00:00');
    $ruh = Branch::factory()->riyadh()->create();
    $jed = Branch::factory()->riyadh()->create();
    $position = Position::factory()->create();
    $employee = hireInto($ruh);

    resolve(ChangePlacement::class)->handle(Actor::system('test'), $employee, new PlacementInputData($jed->ulid, positionId: $position->ulid), '2026-11-01', 'transfer');

    $rows = $employee->assignments()->orderBy('effective_from')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows[0]->effective_to?->toDateString())->toBe('2026-10-31')
        ->and($rows[1]->is_current)->toBeFalse()
        ->and($employee->fresh()?->current_branch_id)->toBe($ruh->id);

    expect(fn () => resolve(ChangePlacement::class)->handle(Actor::system('test'), $employee, new PlacementInputData($ruh->ulid), '2026-10-20'))
        ->toThrow(DomainRuleViolation::class, 'later change');

    $this->travelTo('2026-11-01 09:00:00');
    resolve(SyncEmployeePlacement::class)->handle($employee->fresh() ?? $employee);

    expect($employee->fresh())->current_branch_id->toBe($jed->id)->current_position_id->toBe($position->id);

    expect(fn () => EmployeeAssignment::query()->create([
        'employee_id' => $employee->id, 'branch_id' => $ruh->id, 'is_primary' => true, 'effective_from' => '2026-12-01',
    ]))->toThrow(QueryException::class);
});

it('maintains the reporting closure table through manager changes', function (): void {
    $branch = Branch::factory()->create();
    $ceo = hireInto($branch, ['nameEn' => 'CEO']);
    $vp = hireInto($branch, ['nameEn' => 'VP'], $ceo);
    $lead = hireInto($branch, ['nameEn' => 'Lead'], $vp);
    $dev = hireInto($branch, ['nameEn' => 'Dev'], $lead);
    $cfo = hireInto($branch, ['nameEn' => 'CFO'], $ceo);

    expect(DB::table('employee_reporting_paths')->where('ancestor_id', $ceo->id)->count())->toBe(5)
        ->and(DB::table('employee_reporting_paths')->where('ancestor_id', $vp->id)->where('descendant_id', $dev->id)->value('depth'))->toBe(2);

    // Lead (and Dev under them) move from VP to CFO.
    resolve(ChangePlacement::class)->handle(Actor::system('test'), $lead, new PlacementInputData($branch->ulid, managerId: $cfo->ulid), now()->toDateString());

    expect(DB::table('employee_reporting_paths')->where('ancestor_id', $vp->id)->pluck('descendant_id')->all())->toBe([$vp->id])
        ->and(DB::table('employee_reporting_paths')->where('ancestor_id', $cfo->id)->where('descendant_id', $dev->id)->value('depth'))->toBe(2)
        ->and(DB::table('employee_reporting_paths')->where('ancestor_id', $ceo->id)->where('descendant_id', $dev->id)->value('depth'))->toBe(3);

    expect(fn () => resolve(ChangePlacement::class)->handle(Actor::system('test'), $ceo, new PlacementInputData($branch->ulid, managerId: $dev->ulid), now()->toDateString()))
        ->toThrow(DomainRuleViolation::class);

    $before = paths();
    expect(fn () => resolve(ChangePlacement::class)->handle(Actor::system('test'), $cfo, new PlacementInputData($branch->ulid, managerId: $cfo->ulid), now()->toDateString()))
        ->toThrow(DomainRuleViolation::class);
    expect(paths())->toBe($before);
});

it('adds secondary placements without touching the reporting line', function (): void {
    $home = Branch::factory()->create();
    $other = Branch::factory()->create();
    $manager = hireInto($home, ['nameEn' => 'Manager']);
    $employee = hireInto($home, [], $manager);

    $secondary = resolve(AddSecondaryAssignment::class)->handle(Actor::system('test'), $employee, new PlacementInputData($other->ulid, managerId: $employee->ulid), now()->subDay()->toDateString());

    expect($secondary->fresh())->is_primary->toBeFalse()->manager_employee_id->toBeNull()->is_current->toBeTrue()
        ->and($employee->fresh())->current_branch_id->toBe($home->id)->current_manager_id->toBe($manager->id);
});

it('terminates on the last working day', function (): void {
    $this->travelTo('2026-10-01 09:00:00');
    $employee = hireInto(Branch::factory()->create());

    resolve(TerminateEmployee::class)->handle($employee, '2026-10-10', 'Resigned');
    expect($employee->fresh())->status->toBe(EmployeeStatus::Active)->termination_date->toDateString()->toBe('2026-10-10');

    $this->travelTo('2026-10-11 12:00:00');
    $this->artisan('people:sync-placements')->assertSuccessful();

    expect($employee->fresh())->status->toBe(EmployeeStatus::Terminated)
        ->and($employee->assignments()->sole()->effective_to?->toDateString())->toBe('2026-10-10')
        ->and(fn (): Employee => hireInto(Branch::factory()->create(), [], $employee->fresh()))->toThrow(DomainRuleViolation::class, 'still works here');
});
