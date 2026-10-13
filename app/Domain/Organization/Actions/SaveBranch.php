<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Data\BranchInputData;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\CostCenter;
use App\Domain\Organization\Support\ReferenceValues;

/**
 * Creates or edits a branch. Its timezone, weekend and working hours drive every branch-local date
 * downstream (ADR-0009), so they are required up front.
 */
final readonly class SaveBranch
{
    public function handle(BranchInputData $input, ?Branch $branch = null): Branch
    {
        $branch ??= new Branch;
        $code = ReferenceValues::code($input->code);

        ReferenceValues::assertCodeAvailable(Branch::class, $code, $branch);

        $weekend = array_values(array_unique(array_map(intval(...), $input->weekendDays)));
        sort($weekend);

        $branch->setTranslations('name', ReferenceValues::translations($input->name));
        $branch->fill([
            'code' => $code,
            'address' => $input->address === null ? null : array_filter($input->address, fn (mixed $value): bool => $value !== null && $value !== ''),
            'country_code' => mb_strtoupper($input->countryCode),
            'timezone' => $input->timezone,
            'default_currency' => mb_strtoupper($input->defaultCurrency),
            'phone_region' => mb_strtoupper($input->phoneRegion),
            'weekend_days' => $weekend,
            'work_starts_at' => $input->workStartsAt,
            'work_ends_at' => $input->workEndsAt,
            'geofence_lat' => $input->geofence === null ? null : round((float) $input->geofence['lat'], 6),
            'geofence_lng' => $input->geofence === null ? null : round((float) $input->geofence['lng'], 6),
            'geofence_radius_m' => $input->geofence === null ? null : (int) $input->geofence['radiusM'],
            'cost_center_id' => $input->costCenterId === null ? null : CostCenter::query()->where('ulid', $input->costCenterId)->value('id'),
            'is_active' => $input->isActive,
        ]);
        $branch->save();

        return $branch;
    }
}
