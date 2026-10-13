<?php

declare(strict_types=1);

use App\Domain\Organization\Actions\DeleteDepartment;
use App\Domain\Organization\Actions\MoveDepartment;
use App\Domain\Organization\Actions\SaveDepartment;
use App\Domain\Organization\Data\DepartmentInputData;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Position;
use App\Support\Errors\DomainRuleViolation;

function department(string $code, ?Department $parent = null): Department
{
    return resolve(SaveDepartment::class)->handle(new DepartmentInputData(
        code: $code,
        name: ['en' => "{$code} dept", 'ar' => "قسم {$code}"],
        parentId: $parent?->ulid,
    ));
}

it('builds ltree paths from ancestor ids', function (): void {
    $ops = department('ops');
    $field = department('field', $ops);
    $north = department('north', $field);

    expect($ops->path)->toBe((string) $ops->id)
        ->and($field->path)->toBe("{$ops->id}.{$field->id}")
        ->and($north->path)->toBe("{$ops->id}.{$field->id}.{$north->id}")
        ->and($north->depth)->toBe(2)
        ->and($north->code)->toBe('NORTH');

    expect(Department::query()->withinSubtree($field)->pluck('code')->all())->toEqualCanonicalizing(['FIELD', 'NORTH']);
});

it('moves a whole subtree when a department is reparented', function (): void {
    $ops = department('ops');
    $field = department('field', $ops);
    $north = department('north', $field);
    $sales = department('sales');

    resolve(MoveDepartment::class)->handle($field, $sales);

    expect($field->fresh())->path->toBe("{$sales->id}.{$field->id}")->depth->toBe(1)
        ->and($north->fresh())->path->toBe("{$sales->id}.{$field->id}.{$north->id}")->depth->toBe(2)
        ->and($field->fresh()?->parent_id)->toBe($sales->id);

    resolve(MoveDepartment::class)->handle($field, null);

    expect($field->fresh())->path->toBe((string) $field->id)->depth->toBe(0)
        ->and($north->fresh()?->path)->toBe("{$field->id}.{$north->id}");
});

it('reparents through SaveDepartment as well', function (): void {
    $ops = department('ops');
    $field = department('field', $ops);
    $sales = department('sales');

    resolve(SaveDepartment::class)->handle(new DepartmentInputData(code: 'field', name: ['en' => 'Field'], parentId: $sales->ulid), $field);

    expect($field->fresh()?->path)->toBe("{$sales->id}.{$field->id}");
});

it('refuses to build a cycle', function (): void {
    $ops = department('ops');
    $field = department('field', $ops);
    $north = department('north', $field);

    expect(fn () => resolve(MoveDepartment::class)->handle($ops, $north))
        ->toThrow(DomainRuleViolation::class)
        ->and(fn () => resolve(MoveDepartment::class)->handle($ops, $ops))
        ->toThrow(DomainRuleViolation::class);

    expect($ops->fresh()?->path)->toBe((string) $ops->id);
});

it('archives only empty departments', function (): void {
    $ops = department('ops');
    $field = department('field', $ops);
    Position::factory()->create(['department_id' => $field->id]);

    expect(fn () => resolve(DeleteDepartment::class)->handle($ops))->toThrow(DomainRuleViolation::class, 'sub-departments')
        ->and(fn () => resolve(DeleteDepartment::class)->handle($field))->toThrow(DomainRuleViolation::class, 'positions');

    Position::query()->delete();
    resolve(DeleteDepartment::class)->handle($field);
    resolve(DeleteDepartment::class)->handle($ops);

    expect(Department::query()->count())->toBe(0)
        ->and(Department::withTrashed()->count())->toBe(2);
});

it('keeps codes unique, archived rows included', function (): void {
    $ops = department('ops');
    $ops->delete();

    expect(fn (): Department => department('OPS'))->toThrow(DomainRuleViolation::class);
});
