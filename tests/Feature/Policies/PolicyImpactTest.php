<?php

declare(strict_types=1);

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\People\Enums\EmployeeStatus;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Actions\AssignPolicySet;
use App\Domain\Policies\Actions\SavePolicySet;
use App\Domain\Policies\Actions\SeedPolicyDefaults;
use App\Domain\Policies\Actions\SetPolicyValues;
use App\Domain\Policies\Data\ImpactChangeData;
use App\Domain\Policies\Data\ImpactPreviewData;
use App\Domain\Policies\Data\ImpactRowData;
use App\Domain\Policies\Enums\PolicyLevel;
use App\Domain\Policies\Models\PolicySet;
use App\Domain\Policies\Support\PolicyImpact;
use App\Domain\Policies\Support\PolicyResolver;
use App\Support\Actors\Actor;

beforeEach(function (): void {
    $this->travelTo('2026-10-15 09:00:00');
    resolve(SeedPolicyDefaults::class)->handle();
    $this->actor = Actor::system('test');
    $this->riyadh = Branch::factory()->riyadh()->create();
    $this->dubai = Branch::factory()->create();
    $this->ops = Department::factory()->create();
    $this->field = Department::factory()->childOf($this->ops)->create();

    $this->inField = Employee::factory()->placedIn($this->riyadh, $this->field)->create(['name_en' => 'A Field']);
    $this->inOps = Employee::factory()->placedIn($this->riyadh, $this->ops)->create(['name_en' => 'B Ops']);
    $this->inDubai = Employee::factory()->placedIn($this->dubai)->create(['name_en' => 'C Dubai']);
    Employee::factory()->placedIn($this->riyadh, $this->field)->create(['status' => EmployeeStatus::Terminated]);

    $this->set = resolve(SavePolicySet::class)->handle($this->actor, ['en' => 'Riyadh']);
    resolve(SetPolicyValues::class)->handle($this->actor, $this->set, ['attendance.grace' => ['lateMinutes' => 10]], '2026-01-01', 'test');
    resolve(AssignPolicySet::class)->handle($this->actor, $this->set, PolicyLevel::Branch, $this->riyadh->id, '2026-01-01', 'test');
});

function affectedNames(ImpactPreviewData $preview): array
{
    return array_map(fn (ImpactRowData $row): string => $row->nameEn, $preview->rows);
}

it('previews a value change for the employees the set reaches, minus those overridden', function (): void {
    $field = resolve(SavePolicySet::class)->handle($this->actor, ['en' => 'Field']);
    resolve(SetPolicyValues::class)->handle($this->actor, $field, ['attendance.grace' => ['lateMinutes' => 0]], '2026-01-01', 'test');
    resolve(AssignPolicySet::class)->handle($this->actor, $field, PolicyLevel::Department, $this->field->id, '2026-01-01', 'test');

    $preview = resolve(PolicyImpact::class)->ofValues($this->set, ['attendance.grace' => ['lateMinutes' => 5, 'earlyLeaveMinutes' => 0, 'countFrom' => 'scheduled']], '2026-11-01');

    expect($preview->checked)->toBe(2)
        ->and($preview->affected)->toBe(1)
        ->and(affectedNames($preview))->toBe(['B Ops'])
        ->and($preview->rows[0]->changes[0])->toBeInstanceOf(ImpactChangeData::class)
        ->and($preview->rows[0]->changes[0]->before['lateMinutes'])->toBe(10)
        ->and($preview->rows[0]->changes[0]->after['lateMinutes'])->toBe(5);
});

it('previews assigning a set to a department subtree and removing a branch assignment', function (): void {
    $strict = resolve(SavePolicySet::class)->handle($this->actor, ['en' => 'Strict']);
    resolve(SetPolicyValues::class)->handle($this->actor, $strict, ['attendance.grace' => ['lateMinutes' => 0]], '2026-11-01');

    $assign = resolve(PolicyImpact::class)->ofAssignment(PolicyLevel::Department, $this->ops->id, $strict, '2026-11-01');
    expect(affectedNames($assign))->toBe(['A Field', 'B Ops'])
        ->and($assign->rows[0]->changes[0]->afterLevel)->toBe('department');

    $end = resolve(PolicyImpact::class)->ofAssignment(PolicyLevel::Branch, $this->riyadh->id, null, '2026-11-01');
    expect($end->affected)->toBe(2)->and($end->rows[0]->changes[0]->afterLevel)->toBe('global');
});

it('reports nobody when the change matches what people already get, and saves nothing', function (): void {
    $preview = resolve(PolicyImpact::class)->ofValues($this->set, ['attendance.grace' => ['lateMinutes' => 10, 'earlyLeaveMinutes' => 0, 'countFrom' => 'scheduled']], '2026-11-01');

    expect($preview->affected)->toBe(0)
        ->and(resolve(PolicyResolver::class)->get($this->inOps, '2026-11-01', 'attendance.grace')->value['lateMinutes'])->toBe(10)
        ->and(PolicySet::query()->count())->toBe(2);
});

it('resolves a large preview in a bounded number of queries', function (): void {
    Employee::factory()->count(40)->placedIn($this->riyadh, $this->ops)->create();
    app()->forgetScopedInstances();

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $preview = resolve(PolicyImpact::class)->ofAssignment(PolicyLevel::Branch, $this->riyadh->id, null, '2026-11-01');

    expect($preview->affected)->toBe(42)->and($queries)->toBeLessThan(15);
});
