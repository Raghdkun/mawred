<?php

declare(strict_types=1);

use App\Domain\Requests\Actions\SeedRequestTypes;
use App\Domain\Requests\Models\RequestType;
use App\Domain\Workflows\Models\WorkflowDefinition;

beforeEach(fn () => seedAccess());

it('seeds the 16 request types with published forms and workflows, idempotently', function (): void {
    resolve(SeedRequestTypes::class)->handle();
    resolve(SeedRequestTypes::class)->handle();

    $types = RequestType::query()->with(['form', 'workflow'])->get();

    expect($types)->toHaveCount(16)
        ->and($types->filter(fn (RequestType $type): bool => $type->isAvailable())->pluck('code')->sort()->values()->all())
        ->toBe(['ANNUAL_LEAVE', 'ASSET_REQUEST', 'ATTENDANCE_CORRECTION', 'BUSINESS_TRIP', 'DAY_OFF', 'DETAILS_CHANGE', 'EXPENSE', 'EXPERIENCE_LETTER', 'JOB_REQUISITION', 'OVERTIME', 'RESIGNATION', 'SALARY_ADVANCE', 'SALARY_CERTIFICATE', 'SHIFT_SWAP', 'SICK_LEAVE', 'TRAINING'])
        ->and($types->every(fn (RequestType $type): bool => $type->form?->published_version_id !== null && $type->workflow?->published_version_id !== null))->toBeTrue()
        ->and(WorkflowDefinition::query()->count())->toBe(16);
});

it('upgrades a type seeded before its module shipped to the shipped form and workflow', function (): void {
    resolve(SeedRequestTypes::class)->handle();
    $swap = RequestType::query()->where('code', 'SHIFT_SWAP')->sole();
    $swap->forceFill(['available_in_phase' => 5])->save();
    $before = $swap->workflow()->firstOrFail()->published_version_id;

    resolve(SeedRequestTypes::class)->handle();
    resolve(SeedRequestTypes::class)->handle();

    expect($swap->fresh()?->available_in_phase)->toBe(0)
        ->and($swap->workflow()->firstOrFail()->published_version_id)->not->toBe($before)
        ->and(WorkflowDefinition::query()->count())->toBe(16);
});
