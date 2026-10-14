<?php

declare(strict_types=1);

use App\Domain\Access\Models\User;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    seedAccess();
    $this->riyadh = Branch::factory()->riyadh()->create(['code' => 'RUH', 'name' => ['en' => 'Riyadh HQ', 'ar' => 'المقر الرئيسي']]);
    $this->jeddah = Branch::factory()->riyadh()->create(['code' => 'JED', 'name' => ['en' => 'Jeddah', 'ar' => 'جدة']]);
    $this->managerUser = userWithRoles('branch_manager');
    Employee::factory()->placedIn($this->riyadh)->create(['user_id' => $this->managerUser->id, 'name_en' => 'Branch Boss']);
    $this->colleague = Employee::factory()->placedIn($this->riyadh)->create(['name_en' => 'Fatimah Zahrani', 'name_ar' => 'فاطمة الزهراني', 'work_phone_e164' => '+966501234567', 'national_id' => '1098765432']);
    $this->stranger = Employee::factory()->placedIn($this->jeddah)->create(['name_en' => 'Fatimah Other']);
});

/**
 * @return array<string, list<array<string, mixed>>>
 */
function searchFor(object $test, string $q): array
{
    $response = $test->getJson('/api/v1/search?q='.rawurlencode($q))->assertOk();

    return collect($response->json('data'))->mapWithKeys(fn (array $group): array => [$group['type'] => $group['items']])->all();
}

it('finds people in scope only, in either language, by phone, and branches', function (): void {
    Sanctum::actingAs($this->managerUser);

    expect(collect(searchFor($this, 'fatimah')['employees'])->pluck('id')->all())->toBe([$this->colleague->ulid])
        ->and(searchFor($this, 'فاطمه')['employees'][0]['id'])->toBe($this->colleague->ulid)
        ->and(searchFor($this, '0501234567')['employees'][0]['url'])->toBe("/people/{$this->colleague->ulid}")
        ->and(searchFor($this, 'jedd')['branches'][0]['title'])->toBe('Jeddah')
        // National IDs only match exactly, and only for people who may read them.
        ->and(searchFor($this, '1098765432'))->not->toHaveKey('employees');
});

it('matches a national ID exactly for HR', function (): void {
    $hr = userWithRoles('hr_admin');
    $hr->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
    Sanctum::actingAs($hr);

    expect(searchFor($this, '1098-765-432')['employees'][0]['id'])->toBe($this->colleague->ulid)
        ->and(searchFor($this, '109876'))->not->toHaveKey('employees');
});

it('searches nothing the caller cannot see', function (): void {
    Sanctum::actingAs(User::factory()->create());

    expect(searchFor($this, 'fatimah'))->toBe([]);
    $this->getJson('/api/v1/search?q=a')->assertUnprocessable();
});
