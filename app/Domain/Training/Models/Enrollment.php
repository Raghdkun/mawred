<?php

declare(strict_types=1);

namespace App\Domain\Training\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Domain\People\Models\Employee;
use App\Domain\Training\Enums\EnrollmentSource;
use App\Domain\Training\Enums\EnrollmentStatus;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Database\Factories\Training\EnrollmentFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Someone on a course: requested (waiting for approval, D99), enrolled with their progress
 * through the modules, then completed. Mandatory courses set a due date (D100).
 *
 * @property int $id
 * @property string $ulid
 * @property int $employee_id
 * @property int $course_id
 * @property int|null $learning_path_id
 * @property EnrollmentStatus $status
 * @property EnrollmentSource $source
 * @property int|null $assigned_by
 * @property CarbonImmutable|null $due_on
 * @property int $progress
 * @property int|null $workflow_instance_id
 * @property string|null $decision_note
 * @property CarbonImmutable|null $requested_at
 * @property CarbonImmutable|null $enrolled_at
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable $created_at
 */
#[Guarded(['id', 'ulid'])]
#[UseFactory(EnrollmentFactory::class)]
final class Enrollment extends Model
{
    use Auditable;

    /** @use HasFactory<EnrollmentFactory> */
    use HasFactory;

    use HasPublicUlid;

    /** @var array<string, mixed> */
    protected $attributes = ['progress' => 0];

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

    /**
     * @return BelongsTo<LearningPath, $this>
     */
    public function learningPath(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class);
    }

    /**
     * @return HasMany<ModuleProgress, $this>
     */
    public function moduleProgress(): HasMany
    {
        return $this->hasMany(ModuleProgress::class);
    }

    /**
     * @return HasMany<QuizAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /**
     * @return HasMany<SessionBooking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(SessionBooking::class);
    }

    /** Past its due date and not finished. */
    public function isOverdue(string $today): bool
    {
        return $this->status === EnrollmentStatus::Enrolled && $this->due_on !== null && $this->due_on->toDateString() < $today;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'source' => EnrollmentSource::class,
            'due_on' => 'immutable_date',
            'requested_at' => 'immutable_datetime',
            'enrolled_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
