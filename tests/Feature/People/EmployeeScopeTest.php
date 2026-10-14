<?php

declare(strict_types=1);

use App\Domain\Access\Actions\AssignRoles;
use App\Domain\Access\Actions\CloneRole;
use App\Domain\Access\Actions\SyncRoleGrants;
use App\Domain\Access\Enums\Scope;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\People\Actions\AddSecondaryAssignment;
use App\Domain\People\Actions\SyncEmployeePlacement;
use App\Domain\People\Data\PlacementInputData;
use App\Domain\People\Models\Employee;
use App\Domain\People\Queries\EmployeeDirectoryQuery;
use App\Support\Actors\Actor;

/*
| Scope-leak dataset (ADR-0005). Branches A and B; departments Ops > Field, and Sales.
|
|   mgr         A / Ops     (the viewer)
|   report1     A / Ops     reports to mgr
|   report2     A / Field   reports to report1
|   peerDept    A / Field
|   peerBranch  A / Sales
|   otherBranch B / Sales
|   secondary   B / Sales   + secondary placement in A / Field
*/
beforeEach(function (): void {
    seedAccess();

    $this->a = Branch::factory()->create(['code' => 'A']);
    $this->b = Branch::factory()->create(['code' => 'B']);
    $this->ops = Department::factory()->create(['code' => 'OPS']);
    $this->field = Department::factory()->childOf($this->ops)->create(['code' => 'FIELD']);
    $this->sales = Department::factory()->create(['code' => 'SALES']);

    $this->viewer = User::factory()->create();
    $people = [];
    $people['mgr'] = Employee::factory()->placedIn($this->a, $this->ops)->create(['user_id' => $this->viewer->id]);
    $people['report1'] = Employee::factory()->placedIn($this->a, $this->ops, $people['mgr'])->create();
    $people['report2'] = Employee::factory()->placedIn($this->a, $this->field, $people['report1'])->create();
    $people['peerDept'] = Employee::factory()->placedIn($this->a, $this->field)->create();
    $people['peerBranch'] = Employee::factory()->placedIn($this->a, $this->sales)->create();
    $people['otherBranch'] = Employee::factory()->placedIn($this->b, $this->sales)->create();
    $people['secondary'] = Employee::factory()->placedIn($this->b, $this->sales)->create();
    resolve(AddSecondaryAssignment::class)->handle(Actor::system('test'), $people['secondary'], new PlacementInputData($this->a->ulid, $this->field->ulid), '2024-01-01');
    $this->people = $people;
});

/**
 * @param  array{branches?: list<int>, departments?: list<int>}  $anchors
 * @return list<string>
 */
function visibleWith(object $test, Scope $scope, array $anchors = [], ?User $viewer = null): array
{
    $role = Role::query()->where('name', 'probe')->first()
        ?? resolve(CloneRole::class)->handle(Role::findByName('employee'), ['en' => 'Probe', 'ar' => 'اختبار']);
    $role->forceFill(['name' => 'probe'])->save();
    resolve(SyncRoleGrants::class)->handle($role, ['people.view' => $scope]);

    $viewer ??= $test->viewer;
    resolve(AssignRoles::class)->handle(Actor::system('test'), $viewer, ['probe' => $anchors]);
    app()->forgetScopedInstances();

    $ids = resolve(EmployeeDirectoryQuery::class)->visibleTo($viewer)->pluck('employees.id')->all();

    return collect($test->people)->filter(fn (Employee $e): bool => in_array($e->id, $ids, true))->keys()->sort()->values()->all();
}

it('limits each scope to exactly the right people', function (Scope $scope, array $expected): void {
    expect(visibleWith($this, $scope))->toBe(collect($expected)->sort()->values()->all());
})->with([
    'own' => [Scope::Own, ['mgr']],
    'direct reports' => [Scope::DirectReports, ['mgr', 'report1']],
    'team tree' => [Scope::TeamTree, ['mgr', 'report1', 'report2']],
    'department subtree' => [Scope::Department, ['mgr', 'report1', 'report2', 'peerDept', 'secondary']],
    'branch' => [Scope::Branch, ['mgr', 'report1', 'report2', 'peerDept', 'peerBranch', 'secondary']],
    'all' => [Scope::All, ['mgr', 'report1', 'report2', 'peerDept', 'peerBranch', 'otherBranch', 'secondary']],
]);

it('uses anchors instead of the viewer placement', function (): void {
    expect(visibleWith($this, Scope::Department, ['departments' => [$this->sales->id]]))->toBe(['mgr', 'otherBranch', 'peerBranch', 'secondary'])
        ->and(visibleWith($this, Scope::Branch, ['branches' => [$this->b->id]]))->toBe(['mgr', 'otherBranch', 'secondary']);
});

it('shows nothing org-relative to an account without an employee record or anchors', function (): void {
    $outsider = User::factory()->create();

    foreach ([Scope::Own, Scope::DirectReports, Scope::TeamTree, Scope::Department, Scope::Branch] as $scope) {
        expect(visibleWith($this, $scope, [], $outsider))->toBe([]);
    }

    expect(visibleWith($this, Scope::Branch, ['branches' => [$this->b->id]], $outsider))->toBe(['otherBranch', 'secondary']);
});

it('stops showing a secondary placement once it ends', function (): void {
    $this->people['secondary']->assignments()->where('is_primary', false)->update(['effective_to' => now()->subDays(2)->toDateString()]);
    resolve(SyncEmployeePlacement::class)->handle($this->people['secondary']);

    expect(visibleWith($this, Scope::Branch))->not->toContain('secondary');
});

it('sorts names in the UI language and pages with a cursor', function (): void {
    $this->people['mgr']->forceFill(['name_ar' => 'ياسر'])->save();
    $this->people['report1']->forceFill(['name_en' => 'Zed', 'name_ar' => 'أحمد'])->save();
    $admin = userWithRoles('hr_admin');

    $arabic = resolve(EmployeeDirectoryQuery::class)->namesIn('ar')->visibleTo($admin)->get()->pluck('ulid')->all();
    $english = resolve(EmployeeDirectoryQuery::class)->namesIn('en')->visibleTo($admin)->get()->pluck('ulid')->all();
    $page = resolve(EmployeeDirectoryQuery::class)->namesIn('ar')->visibleTo($admin)->cursorPaginate(3);

    expect(array_search($this->people['report1']->ulid, $arabic, true))->toBeLessThan(array_search($this->people['mgr']->ulid, $arabic, true))
        ->and(array_search($this->people['report1']->ulid, $english, true))->toBeGreaterThan(array_search($this->people['mgr']->ulid, $english, true))
        ->and($page->nextCursor())->not->toBeNull();
});
