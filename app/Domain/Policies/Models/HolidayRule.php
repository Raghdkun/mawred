<?php

declare(strict_types=1);

namespace App\Domain\Policies\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Domain\Policies\Enums\HolidayType;
use App\Domain\Policies\Enums\ObservedRule;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * One holiday rule (D12): fixed month/day, nth weekday of a month, or a one-off date, optionally
 * several days long and shifted when it falls on a weekend.
 *
 * @property int $id
 * @property string $ulid
 * @property int $holiday_calendar_id
 * @property array<string, string>|string $name
 * @property HolidayType $type
 * @property int|null $month
 * @property int|null $day
 * @property int|null $weekday ISO 1-7
 * @property int|null $nth 1-5, or -1 for the last
 * @property CarbonImmutable|null $date
 * @property int $duration_days
 * @property ObservedRule $observed
 * @property bool $hijri_based
 * @property int|null $from_year
 * @property int|null $to_year
 */
#[Guarded(['id', 'ulid'])]
final class HolidayRule extends Model
{
    use Auditable;
    use HasPublicUlid;
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['name'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'month' => null, 'day' => null, 'weekday' => null, 'nth' => null, 'date' => null,
        'duration_days' => 1, 'observed' => 'none', 'hijri_based' => false, 'from_year' => null, 'to_year' => null,
    ];

    /**
     * @return BelongsTo<HolidayCalendar, $this>
     */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(HolidayCalendar::class, 'holiday_calendar_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => HolidayType::class,
            'observed' => ObservedRule::class,
            'date' => 'immutable_date',
            'month' => 'integer', 'day' => 'integer', 'weekday' => 'integer', 'nth' => 'integer',
            'duration_days' => 'integer', 'from_year' => 'integer', 'to_year' => 'integer',
            'hijri_based' => 'boolean',
        ];
    }
}
