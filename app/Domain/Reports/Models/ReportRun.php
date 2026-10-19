<?php

declare(strict_types=1);

namespace App\Domain\Reports\Models;

use App\Domain\Access\Models\User;
use App\Domain\Reports\Enums\ReportFormat;
use App\Domain\Reports\Enums\RunStatus;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One export of a report (D113, D114): made on the `reports` queue as the person who asked,
 * kept for a week, and downloadable only by them.
 *
 * @property int $id
 * @property string $ulid
 * @property int $user_id
 * @property string $report
 * @property array<string, mixed> $params
 * @property ReportFormat $format
 * @property string $locale
 * @property RunStatus $status
 * @property int|null $rows
 * @property string|null $file_path
 * @property string|null $file_name
 * @property string|null $error
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable $created_at
 */
#[Guarded(['id', 'ulid'])]
final class ReportRun extends Model
{
    use HasPublicUlid;

    public const string DISK = 'local';

    public const int KEEP_DAYS = 7;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'queued', 'locale' => 'en'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'params' => 'array',
            'format' => ReportFormat::class,
            'status' => RunStatus::class,
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
