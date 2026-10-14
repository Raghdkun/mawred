<?php

declare(strict_types=1);

namespace App\Domain\Policies\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Domain\Organization\Models\Branch;
use App\Support\Ids\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

/**
 * A shared holiday calendar (D47), e.g. "Saudi public holidays", assigned to many branches.
 *
 * @property int $id
 * @property string $ulid
 * @property string $code
 * @property array<string, string>|string $name
 * @property string|null $country_code
 * @property string|null $preset_key
 * @property bool $is_active
 */
#[Guarded(['id', 'ulid'])]
final class HolidayCalendar extends Model
{
    use Auditable;
    use HasPublicUlid;
    use HasTranslations;
    use SoftDeletes;

    /** @var list<string> */
    public array $translatable = ['name'];

    /** @var array<string, mixed> */
    protected $attributes = ['country_code' => null, 'preset_key' => null, 'is_active' => true];

    /**
     * @return HasMany<HolidayRule, $this>
     */
    public function rules(): HasMany
    {
        return $this->hasMany(HolidayRule::class);
    }

    /**
     * @return BelongsToMany<Branch, $this>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_holiday_calendar');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
