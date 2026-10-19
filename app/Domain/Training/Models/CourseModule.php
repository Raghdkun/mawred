<?php

declare(strict_types=1);

namespace App\Domain\Training\Models;

use App\Domain\Training\Enums\ModuleKind;
use App\Support\Ids\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * One step of a course (D98): a text lesson, a video link, a file, or a quiz with a pass mark and
 * a number of attempts (D102). Optional modules don't count towards completion.
 *
 * @property int $id
 * @property string $ulid
 * @property int $course_id
 * @property int $position
 * @property ModuleKind $kind
 * @property array<string, string>|string $title
 * @property array<string, string>|string|null $body
 * @property string|null $url
 * @property string|null $file_path
 * @property string|null $file_name
 * @property int|null $duration_minutes
 * @property bool $required
 * @property int|null $pass_mark
 * @property int|null $max_attempts
 */
#[Guarded(['id', 'ulid'])]
final class CourseModule extends Model
{
    use HasPublicUlid;
    use HasTranslations;

    public const string DISK = 'local';

    public const int DEFAULT_PASS_MARK = 70;

    public const int DEFAULT_ATTEMPTS = 3;

    /** @var list<string> */
    public array $translatable = ['title', 'body'];

    /** @var array<string, mixed> */
    protected $attributes = ['required' => true];

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return HasMany<QuizQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('position');
    }

    public function passMark(): int
    {
        return $this->pass_mark ?? self::DEFAULT_PASS_MARK;
    }

    public function attemptsAllowed(): int
    {
        return $this->max_attempts ?? self::DEFAULT_ATTEMPTS;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ModuleKind::class,
            'required' => 'boolean',
        ];
    }
}
