<?php

declare(strict_types=1);

use App\Domain\Access\Models\User;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Position;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => seedAccess());

it('needs organization.view', function (string $endpoint): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson($endpoint)->assertForbidden()->assertJsonPath('error.code', 'forbidden');
})->with(['/api/v1/branches', '/api/v1/departments', '/api/v1/positions']);

it('searches branches across both languages, ignoring Arabic letter variants', function (): void {
    Branch::factory()->create(['code' => 'RUH', 'name' => ['en' => 'Riyadh HQ', 'ar' => 'الإدارة العامة']]);
    Branch::factory()->create(['code' => 'JED', 'name' => ['en' => 'Jeddah', 'ar' => 'جدة']]);
    Branch::factory()->create(['code' => 'OLD', 'is_active' => false]);
    Sanctum::actingAs(userWithRoles('branch_manager'));

    $this->getJson('/api/v1/branches?search='.rawurlencode('الادارة'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'RUH');
    $this->getJson('/api/v1/branches?search=jedd')->assertJsonPath('data.0.name.ar', 'جدة');
    $this->getJson('/api/v1/branches')->assertJsonCount(2, 'data');
    $this->getJson('/api/v1/branches?active=0')->assertJsonCount(3, 'data');
});

it('pages departments in tree order', function (): void {
    $root = Department::factory()->create(['code' => 'B-ROOT']);
    Department::factory()->childOf($root)->create(['code' => 'A-CHILD']);
    Department::factory()->create(['code' => 'C-OTHER']);
    Sanctum::actingAs(userWithRoles('branch_manager'));

    $first = $this->getJson('/api/v1/departments?per_page=2')->assertOk();
    $codes = collect($first->json('data'))->pluck('code')->all();

    expect($codes[0])->toBe('B-ROOT')->and($codes[1])->toBe('A-CHILD')
        ->and($first->json('data.1.parent_id'))->toBe($root->ulid);

    $this->getJson('/api/v1/departments?per_page=2&cursor='.$first->json('meta.next_cursor'))
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'C-OTHER');
});

it('narrows positions to a department', function (): void {
    $department = Department::factory()->create();
    Position::factory()->create(['code' => 'IN', 'department_id' => $department->id]);
    Position::factory()->create(['code' => 'OUT']);
    Sanctum::actingAs(userWithRoles('branch_manager'));

    $this->getJson('/api/v1/positions?department='.$department->ulid)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'IN');
});
