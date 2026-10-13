<?php

declare(strict_types=1);

namespace App\Domain\Organization\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Support\Ids\HasPublicUlid;
use App\Support\Search\HasSearchText;
use Database\Factories\Organization\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $ulid
 * @property string $code
 * @property array<string, string>|string $name
 * @property array{line1?: string|null, line2?: string|null, city?: string|null, postal_code?: string|null}|null $address
 * @property string $country_code
 * @property string $timezone
 * @property string $default_currency
 * @property string $phone_region
 * @property list<int> $weekend_days
 * @property string $work_starts_at
 * @property string $work_ends_at
 * @property string|null $geofence_lat
 * @property string|null $geofence_lng
 * @property int|null $geofence_radius_m
 * @property int|null $manager_employee_id
 * @property int|null $cost_center_id
 * @property bool $is_active
 */
#[UseFactory(BranchFactory::class)]
#[Guarded(['id', 'ulid', 'search_text'])]
final class Branch extends Model
{
    use Auditable;

    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    use HasPublicUlid;
    use HasSearchText;
    use HasTranslations;
    use SoftDeletes;

    /** @var list<string> */
    public array $translatable = ['name'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'address' => null,
        'manager_employee_id' => null,
        'cost_center_id' => null,
        'geofence_lat' => null,
        'geofence_lng' => null,
        'geofence_radius_m' => null,
        'is_active' => true,
        'work_starts_at' => '09:00',
        'work_ends_at' => '17:00',
    ];

    /**
     * @return BelongsTo<CostCenter, $this>
     */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function hasGeofence(): bool
    {
        return $this->geofence_lat !== null && $this->geofence_lng !== null && $this->geofence_radius_m !== null;
    }

    public function searchableValues(): array
    {
        return [$this->code, $this->getTranslations('name'), $this->address['city'] ?? null];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'address' => 'array',
            'weekend_days' => 'array',
            'is_active' => 'boolean',
            'geofence_radius_m' => 'integer',
        ];
    }
}
