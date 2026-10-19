<?php

declare(strict_types=1);

namespace App\Domain\Training\Models;

use App\Domain\Access\Models\User;
use App\Domain\Audit\Concerns\Auditable;
use App\Domain\Training\Enums\CourseCategory;
use App\Domain\Training\Enums\CourseStatus;
use App\Support\Ids\HasPublicUlid;
use Carbon\CarbonImmutable;
use Database\Factories\Training\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * A course in the catalog (D98): ordered self-paced modules and, optionally, sessions. It can
 * need the manager's approval to enrol (D99), be mandatory for an audience with a due date
 * (D100), and issue a certificate valid for a number of months (D103).
 *
 * @property int $id
 * @property string $ulid
 * @property string $code
 * @property array<string, string>|string $title
 * @property array<string, string>|string|null $summary
 * @property array<string, string>|string|null $description
 * @property CourseCategory $category
 * @property CourseStatus $status
 * @property int|null $duration_minutes
 * @property bool $requires_approval
 * @property bool $issues_certificate
 * @property int|null $validity_months
 * @property array{branches?: list<string>, departments?: list<string>, positions?: list<string>, employment_types?: list<string>, hired_within_days?: int|null}|null $audience
 * @property int|null $due_days
 * @property int|null $owner_user_id
 * @property int|null $created_by
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Guarded(['id', 'ulid'])]
#[UseFactory(CourseFactory::class)]
final class Course extends Model
{
    use Auditable;

    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    use HasPublicUlid;
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['title', 'summary', 'description'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'draft', 'category' => 'skills', 'requires_approval' => false, 'issues_certificate' => true];

    /**
     * @return HasMany<CourseModule, $this>
     */
    public function modules(): HasMany
    {
        return $this->hasMany(CourseModule::class)->orderBy('position');
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** Mandatory for an audience (D100). */
    public function isMandatory(): bool
    {
        return $this->audience !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => CourseCategory::class,
            'status' => CourseStatus::class,
            'requires_approval' => 'boolean',
            'issues_certificate' => 'boolean',
            'audience' => 'array',
            'published_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
