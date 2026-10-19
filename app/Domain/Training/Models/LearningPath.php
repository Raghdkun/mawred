<?php

declare(strict_types=1);

namespace App\Domain\Training\Models;

use App\Domain\Audit\Concerns\Auditable;
use App\Domain\Training\Enums\CourseStatus;
use App\Support\Ids\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Translatable\HasTranslations;

/**
 * An ordered set of courses (handoff 33). Joining a path enrols the person in each course; the
 * stepper shows how far they are.
 *
 * @property int $id
 * @property string $ulid
 * @property array<string, string>|string $title
 * @property array<string, string>|string|null $description
 * @property CourseStatus $status
 * @property int|null $created_by
 */
#[Guarded(['id', 'ulid'])]
final class LearningPath extends Model
{
    use Auditable;
    use HasPublicUlid;
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['title', 'description'];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'draft'];

    /**
     * @return BelongsToMany<Course, $this>
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'learning_path_courses')->withPivot('position')->orderByPivot('position');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => CourseStatus::class];
    }
}
