<?php

declare(strict_types=1);

namespace App\Domain\Organization\Data;

use App\Domain\Organization\Models\Branch;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class BranchData extends Data
{
    /**
     * @param  array<string, string>  $name
     * @param  array<string, string>|null  $address
     * @param  list<int>  $weekendDays
     * @param  array{lat: float, lng: float, radiusM: int}|null  $geofence
     */
    public function __construct(
        public string $id,
        public string $code,
        public array $name,
        public ?array $address,
        public string $countryCode,
        public string $timezone,
        public string $defaultCurrency,
        public string $phoneRegion,
        public array $weekendDays,
        public string $workStartsAt,
        public string $workEndsAt,
        public ?array $geofence,
        public ?string $costCenterId,
        public bool $isActive,
    ) {}

    public static function fromBranch(Branch $branch): self
    {
        /** @var array<string, string> $name */
        $name = $branch->getTranslations('name');
        /** @var array<string, string>|null $address */
        $address = $branch->address;

        return new self(
            id: $branch->ulid,
            code: $branch->code,
            name: $name,
            address: $address,
            countryCode: $branch->country_code,
            timezone: $branch->timezone,
            defaultCurrency: $branch->default_currency,
            phoneRegion: $branch->phone_region,
            weekendDays: array_map(intval(...), $branch->weekend_days),
            workStartsAt: substr($branch->work_starts_at, 0, 5),
            workEndsAt: substr($branch->work_ends_at, 0, 5),
            geofence: $branch->hasGeofence() ? [
                'lat' => (float) $branch->geofence_lat,
                'lng' => (float) $branch->geofence_lng,
                'radiusM' => (int) $branch->geofence_radius_m,
            ] : null,
            costCenterId: $branch->costCenter?->ulid,
            isActive: $branch->is_active,
        );
    }
}
