<?php

declare(strict_types=1);

use App\Domain\Access\Models\User;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => seedAccess());

it('lists only the employees in the viewer scope, without sensitive fields', function (): void {
    $mine = Branch::factory()->create();
    $other = Branch::factory()->create();
    $managerUser = userWithRoles('branch_manager');
    Employee::factory()->placedIn($mine)->create(['user_id' => $managerUser->id, 'name_en' => 'Branch Manager']);
    $colleague = Employee::factory()->placedIn($mine)->create(['name_en' => 'Zaid Colleague', 'national_id' => '1099999999']);
    $stranger = Employee::factory()->placedIn($other)->create(['name_en' => 'Zaid Stranger']);
    Sanctum::actingAs($managerUser);

    $this->getJson('/api/v1/employees?search=zaid')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $colleague->ulid)
        ->assertJsonPath('data.0.branch.id', $mine->ulid)
        ->assertJsonMissingPath('data.0.national_id');

    $this->getJson("/api/v1/employees/{$colleague->ulid}")->assertOk()->assertJsonPath('data.employee_number', $colleague->employee_number);
    $this->getJson("/api/v1/employees/{$stranger->ulid}")->assertNotFound()->assertJsonPath('error.code', 'not_found');
});

it('refuses people.view-less callers', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/employees')->assertForbidden();
});
