<?php

declare(strict_types=1);

namespace App\Domain\Organization\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Create / edit a branch (screen 39, setup step 4). Weekend days are ISO weekdays (1 = Monday).
 */
#[TypeScript]
final class BranchInputData extends Data
{
    /**
     * @param  array{en: string, ar?: string|null}  $name
     * @param  array{line1?: string|null, line2?: string|null, city?: string|null, postal_code?: string|null}|null  $address
     * @param  list<int>  $weekendDays
     * @param  array{lat: float, lng: float, radiusM: int}|null  $geofence
     */
    public function __construct(
        public string $code,
        public array $name,
        public string $countryCode,
        public string $timezone,
        public string $defaultCurrency,
        public string $phoneRegion,
        public array $weekendDays,
        public string $workStartsAt,
        public string $workEndsAt,
        public ?array $address = null,
        public ?array $geofence = null,
        public ?string $costCenterId = null,
        public bool $isActive = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/'],
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:120'],
            'name.ar' => ['nullable', 'string', 'max:120'],
            'countryCode' => ['required', 'string', 'size:2', 'alpha'],
            'timezone' => ['required', 'timezone:all'],
            'defaultCurrency' => ['required', 'string', 'size:3', 'alpha'],
            'phoneRegion' => ['required', 'string', 'size:2', 'alpha'],
            'weekendDays' => ['present', 'array', 'max:6'],
            'weekendDays.*' => ['integer', 'between:1,7', 'distinct'],
            'workStartsAt' => ['required', 'date_format:H:i'],
            'workEndsAt' => ['required', 'date_format:H:i', 'different:workStartsAt'],
            'address' => ['nullable', 'array'],
            'address.line1' => ['nullable', 'string', 'max:200'],
            'address.line2' => ['nullable', 'string', 'max:200'],
            'address.city' => ['nullable', 'string', 'max:120'],
            'address.postal_code' => ['nullable', 'string', 'max:20'],
            'geofence' => ['nullable', 'array'],
            'geofence.lat' => ['required_with:geofence', 'numeric', 'between:-90,90'],
            'geofence.lng' => ['required_with:geofence', 'numeric', 'between:-180,180'],
            'geofence.radiusM' => ['required_with:geofence', 'integer', 'between:25,5000'],
            'costCenterId' => ['nullable', 'string', 'exists:cost_centers,ulid'],
            'isActive' => ['boolean'],
        ];
    }
}
