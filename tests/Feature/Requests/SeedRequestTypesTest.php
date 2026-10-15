<?php

declare(strict_types=1);

use App\Domain\Requests\Actions\SeedRequestTypes;
use App\Domain\Requests\Models\RequestType;
use App\Domain\Workflows\Models\WorkflowDefinition;

beforeEach(fn () => seedAccess());

it('seeds the 15 request types with published forms and workflows, idempotently', function (): void {
    resolve(SeedRequestTypes::class)->handle();
    resolve(SeedRequestTypes::class)->handle();

    $types = RequestType::query()->with(['form', 'workflow'])->get();

    expect($types)->toHaveCount(15)
        ->and($types->filter(fn (RequestType $type): bool => $type->isAvailable())->pluck('code')->sort()->values()->all())
        ->toBe(['ANNUAL_LEAVE', 'ASSET_REQUEST', 'BUSINESS_TRIP', 'DAY_OFF', 'DETAILS_CHANGE', 'EXPENSE', 'EXPERIENCE_LETTER', 'RESIGNATION', 'SALARY_ADVANCE', 'SALARY_CERTIFICATE', 'SICK_LEAVE'])
        ->and($types->every(fn (RequestType $type): bool => $type->form?->published_version_id !== null && $type->workflow?->published_version_id !== null))->toBeTrue()
        ->and(WorkflowDefinition::query()->count())->toBe(15);
});
