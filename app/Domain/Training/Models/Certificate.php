<?php

declare(strict_types=1);

namespace App\Domain\Training\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Domain\People\Models\Employee;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * A certificate for a completed course (D103, D110): the holder and course as they were on the
 * day, a public serial for the verification page (D104), and when it expires. Revoked ones stay
 * on record and show as revoked.
 *
 * @property int $id
 * @property string $ulid
 * @property string $serial
 * @property int $employee_id
 * @property int|null $course_id
 * @property int|null $enrollment_id
 * @property array<string, string>|string $title
 * @property array<string, string>|string $holder
 * @property string $locale
 * @property CarbonImmutable $issued_on
 * @property CarbonImmutable|null $expires_on
 * @property list<int> $reminders_sent
 * @property int|null $renewal_enrollment_id
 * @property CarbonImmutable|null $revoked_at
 * @property string|null $revoked_reason
 * @property int|null $revoked_by
 * @property string|null $pdf_path
 */
#[Guarded(['id', 'ulid'])]
final class Certificate extends Model
{
    use Auditable;
    use HasPublicUlid;
    use HasTranslations;

    public const string DISK = 'local';

    /** @var list<string> */
    public array $translatable = ['title', 'holder'];

    /** @var array<string, mixed> */
    protected $attributes = ['reminders_sent' => '[]', 'locale' => 'en'];

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** Valid, expired or revoked on a given day (Y-m-d). */
    public function state(string $today): string
    {
        return match (true) {
            $this->revoked_at !== null => 'revoked',
            $this->expires_on !== null && $this->expires_on->toDateString() < $today => 'expired',
            default => 'valid',
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_on' => 'immutable_date',
            'expires_on' => 'immutable_date',
            'reminders_sent' => 'array',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
