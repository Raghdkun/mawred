<?php

declare(strict_types=1);

namespace App\Domain\Reports\Models;

use App\Domain\Access\Models\User;
use App\Domain\Audit\Concerns\Auditable;
use App\Domain\Reports\Enums\ReportFormat;
use App\Domain\Reports\Enums\ScheduleFrequency;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A report that runs on a schedule for its owner (D120): daily, on a weekday, or on a day of
 * the month, at an hour in the owner's timezone. Each run lands in My exports.
 *
 * @property int $id
 * @property string $ulid
 * @property int $user_id
 * @property string $report
 * @property array<string, mixed> $params
 * @property ReportFormat $format
 * @property ScheduleFrequency $frequency
 * @property int|null $weekday ISO 1–7
 * @property int|null $month_day 1–28
 * @property int $hour 0–23
 * @property string $timezone
 * @property string $locale
 * @property CarbonImmutable $next_run_at
 * @property CarbonImmutable|null $last_run_at
 * @property int|null $last_run_id
 * @property CarbonImmutable|null $paused_at
 * @property string|null $paused_reason
 * @property CarbonImmutable $created_at
 */
#[Guarded(['id', 'ulid'])]
final class ReportSchedule extends Model
{
    use Auditable;
    use HasPublicUlid;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ReportRun, $this>
     */
    public function lastRun(): BelongsTo
    {
        return $this->belongsTo(ReportRun::class, 'last_run_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'params' => 'array',
            'format' => ReportFormat::class,
            'frequency' => ScheduleFrequency::class,
            'next_run_at' => 'immutable_datetime',
            'last_run_at' => 'immutable_datetime',
            'paused_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
