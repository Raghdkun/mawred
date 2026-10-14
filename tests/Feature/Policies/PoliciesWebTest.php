<?php

declare(strict_types=1);

use App\Domain\Access\Models\User;
use App\Domain\Organization\Models\Branch;
use App\Domain\People\Models\Employee;
use App\Domain\Policies\Actions\SavePolicySet;
use App\Domain\Policies\Actions\SeedPolicyDefaults;
use App\Domain\Policies\Actions\SetPolicyValues;
use App\Domain\Policies\Models\HolidayCalendar;
use App\Domain\Policies\Models\LeaveType;
use App\Domain\Policies\Models\PolicyAssignment;
use App\Domain\Policies\Models\PolicySet;
use App\Domain\Policies\Support\HolidayPresets;
use App\Domain\Policies\Support\PolicyKeys;
use App\Support\Actors\Actor;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->travelTo('2026-10-15 09:00:00');
    seedAccess();
    resolve(SeedPolicyDefaults::class)->handle();
    $this->branch = Branch::factory()->riyadh()->create();
});

function policyAdmin(): User
{
    $user = userWithRoles('hr_admin');
    $user->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();

    return $user;
}

function policyOfficer(Branch $branch): User
{
    $user = userWithRoles('hr_officer');
    $user->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
    Employee::factory()->placedIn($branch)->create(['user_id' => $user->id]);

    return $user;
}

it('lists sets with where they are used, and gates the module', function (): void {
    $this->actingAs(policyAdmin())->get(route('policies.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('policies/index')
            ->where('sets.data.0.presetKey', 'neutral-default')
            ->where('sets.data.0.assignments.0.level', 'global')
            ->where('can.create', true));

    $this->actingAs(userWithRoles('employee'))->get(route('policies.index'))->assertForbidden();
});

it('creates a set, edits values from a date and previews the impact first', function (): void {
    $this->actingAs(policyAdmin());
    $this->post(route('policies.store'), ['name' => ['en' => 'Riyadh rules']])->assertRedirect();
    $set = PolicySet::query()->whereNull('preset_key')->sole();

    $this->get(route('policies.show', $set))->assertInertia(fn (Assert $page): Assert => $page
        ->component('policies/show')
        ->where('values', [])
        ->where('weekStartsOn', 7)
        ->has('keys', fn (Assert $keys): Assert => $keys->each(fn (Assert $key): Assert => $key->hasAll(['id', 'section', 'editor', 'default', 'branchFallback', 'affects', 'leaveType']))));

    $values = ['attendance.grace' => ['lateMinutes' => 5, 'earlyLeaveMinutes' => 0, 'countFrom' => 'scheduled']];
    $this->postJson(route('policies.preview', $set), ['values' => $values, 'effectiveFrom' => '2026-11-01'])
        ->assertOk()->assertJsonPath('affected', 0)->assertJsonPath('keys', ['attendance.grace']);

    $this->put(route('policies.values.update', $set), ['values' => ['attendance.grace' => ['lateMinutes' => 999]], 'effectiveFrom' => '2026-11-01'])
        ->assertSessionHasErrors(['values.attendance.grace.lateMinutes', 'error']);
    $this->put(route('policies.values.update', $set), ['values' => $values, 'effectiveFrom' => '2026-01-01'])->assertSessionHasErrors('reason');
    $this->put(route('policies.values.update', $set), ['values' => $values, 'effectiveFrom' => '2026-11-01'])->assertSessionHasNoErrors();

    $this->get(route('policies.show', [$set, 'date' => '2026-10-20']))->assertInertia(fn (Assert $page): Assert => $page
        ->where('values.0.value', null)
        ->where('values.0.scheduled.0.effectiveFrom', '2026-11-01'));

    $this->put(route('policies.update', $set), ['name' => ['en' => 'Riyadh', 'ar' => 'الرياض']])->assertSessionHasNoErrors();
    $this->delete(route('policies.destroy', $set))->assertRedirect(route('policies.index'));
    expect($set->fresh()?->trashed())->toBeTrue();
});

it('exports a set and imports it back as a new set', function (): void {
    $this->actingAs(policyAdmin());
    $neutral = PolicySet::query()->sole();

    $json = $this->get(route('policies.export', $neutral))->assertOk()->assertHeader('Content-Disposition')->getContent();
    $file = UploadedFile::fake()->createWithContent('neutral.mawred-policy.json', (string) $json);

    $this->post(route('policies.import'), ['file' => $file, 'effectiveFrom' => '2026-11-01'])->assertRedirect();
    expect(PolicySet::query()->count())->toBe(2);

    $this->post(route('policies.import'), ['file' => UploadedFile::fake()->createWithContent('x.json', '{"nope":1}'), 'effectiveFrom' => '2026-11-01'])
        ->assertSessionHasErrors('file');
});

it('assigns sets to targets, previews and ends assignments', function (): void {
    $this->actingAs(policyAdmin());
    Employee::factory()->count(2)->placedIn($this->branch)->create();
    $set = resolve(SavePolicySet::class)->handle(Actor::system('test'), ['en' => 'Strict']);
    resolve(SetPolicyValues::class)->handle(Actor::system('test'), $set, ['attendance.grace' => ['lateMinutes' => 0]], '2026-01-01', 'test');

    $payload = ['setId' => $set->ulid, 'level' => 'branch', 'targetId' => $this->branch->ulid, 'effectiveFrom' => '2026-11-01'];
    $this->postJson(route('policies.assignments.preview'), $payload)->assertOk()->assertJsonPath('affected', 2);
    $this->post(route('policies.assignments.store'), [...$payload, 'targetId' => null])->assertSessionHasErrors('targetId');
    $this->post(route('policies.assignments.store'), $payload)->assertSessionHasNoErrors();

    $this->get(route('policies.assignments.index'))->assertInertia(fn (Assert $page): Assert => $page
        ->component('policies/assignments')
        ->where('assignments.1.targetId', $this->branch->ulid)
        ->where('assignments.1.targetName', $this->branch->getTranslation('name', 'en')));

    $assignment = PolicyAssignment::query()->where('level', 'branch')->sole();
    $this->delete(route('policies.assignments.destroy', $assignment), ['endsOn' => '2026-10-31'])->assertSessionHasNoErrors();
    expect($assignment->fresh())->toBeNull();
});

it('explains the effective policy only for employees in scope', function (): void {
    $officer = policyOfficer($this->branch);
    $colleague = Employee::factory()->placedIn($this->branch)->create();
    $stranger = Employee::factory()->placedIn(Branch::factory()->create())->create();

    $this->actingAs($officer)->get(route('policies.effective', ['employee' => $colleague->ulid]))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('policies/effective')
            ->where('employee.id', $colleague->ulid)
            ->has('values', count(resolve(PolicyKeys::class)->all()))
            ->where('values.0.key', 'leave.type.ANNUAL'));

    $this->get(route('policies.effective', ['employee' => $stranger->ulid]))
        ->assertInertia(fn (Assert $page): Assert => $page->where('employee', null)->where('values', []));
});

it('serves an employee’s policies over the API, 404 when out of scope', function (): void {
    $officer = policyOfficer($this->branch);
    $colleague = Employee::factory()->placedIn($this->branch)->create();
    $stranger = Employee::factory()->placedIn(Branch::factory()->create())->create();
    Sanctum::actingAs($officer);

    $this->getJson("/api/v1/employees/{$colleague->ulid}/policies?date=2026-11-01")
        ->assertOk()
        ->assertJsonPath('meta.date', '2026-11-01')
        ->assertJsonPath('data.0.key', 'leave.type.ANNUAL')
        ->assertJsonPath('data.0.chain.0.level', 'global');
    $this->getJson("/api/v1/employees/{$stranger->ulid}/policies")->assertNotFound();
});

it('lets only configurers manage leave types', function (): void {
    $payload = ['code' => 'hajj', 'name' => ['en' => 'Hajj'], 'unit' => 'days'];

    $this->actingAs(policyOfficer($this->branch))->post(route('policies.leave-types.store'), $payload)->assertForbidden();
    $this->actingAs(policyAdmin())->post(route('policies.leave-types.store'), $payload)->assertSessionHasNoErrors();

    $type = LeaveType::query()->where('code', 'HAJJ')->sole();
    $this->put(route('policies.leave-types.update', $type), [...$payload, 'name' => ['en' => 'Hajj leave']])->assertSessionHasNoErrors();
    $this->delete(route('policies.leave-types.destroy', $type))->assertSessionHasNoErrors();

    $this->get(route('policies.leave-types.index'))->assertInertia(fn (Assert $page): Assert => $page->component('policies/leave-types')->where('can.configure', true));
});

it('manages holiday calendars and shows a branch year', function (): void {
    $this->actingAs(policyAdmin());

    $this->post(route('policies.holidays.presets'), ['preset' => HolidayPresets::US_FEDERAL, 'branchIds' => [$this->branch->ulid]])->assertSessionHasNoErrors();
    $this->post(route('policies.holidays.store'), ['code' => 'sa', 'name' => ['en' => 'Saudi'], 'countryCode' => 'sa', 'branchIds' => [$this->branch->ulid]])->assertSessionHasNoErrors();
    $saudi = HolidayCalendar::query()->where('code', 'SA')->sole();

    $this->post(route('policies.holidays.rules.store', $saudi), ['name' => ['en' => 'National Day'], 'type' => 'fixed', 'month' => 9, 'day' => 23])->assertSessionHasNoErrors();
    $this->post(route('policies.holidays.import', $saudi), [
        'file' => UploadedFile::fake()->createWithContent('eid.csv', "date,name_en,days,hijri_based\n2026-03-20,Eid al-Fitr,3,yes\n"),
    ])->assertSessionHasNoErrors();

    $this->get(route('policies.holidays.index', ['branch' => $this->branch->ulid, 'year' => 2026]))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('policies/holidays')
            ->where('branch.weekend', [5, 6])
            ->has('calendars', 2)
            ->where('presets.0.installed', true)
            ->where('days.0.observedOn', '2026-01-01')
            ->where('days', fn ($days): bool => collect($days)->where('hijriBased', true)->pluck('observedOn')->all() === ['2026-03-20', '2026-03-21', '2026-03-22'])
            ->etc());

    $rule = $saudi->rules()->where('type', 'fixed')->sole();
    $other = HolidayCalendar::query()->where('code', 'US_FEDERAL')->sole();
    $this->delete(route('policies.holidays.rules.destroy', [$other, $rule]))->assertNotFound();
    $this->delete(route('policies.holidays.rules.destroy', [$saudi, $rule]))->assertSessionHasNoErrors();
    $this->delete(route('policies.holidays.destroy', $saudi))->assertSessionHasNoErrors();
});

it('completes setup step 5, optionally adding the US federal calendar', function (): void {
    $this->markSetupIncomplete();
    $owner = userWithRoles('super_admin');
    $owner->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();

    $this->actingAs($owner)->get(route('setup.show', ['step' => 'policies']))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('policies.values', fn ($values): bool => $values['attendance.grace']['lateMinutes'] === 15)
            ->where('steps.4.availableInPhase', 0)
            ->etc());

    $this->post(route('setup.steps.policies'), ['holidayPreset' => HolidayPresets::US_FEDERAL])->assertRedirect();

    expect(HolidayCalendar::query()->sole()->branches()->pluck('branches.id')->all())->toBe([$this->branch->id]);
});
