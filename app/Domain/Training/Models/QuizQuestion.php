<?php

declare(strict_types=1);

namespace App\Domain\Training\Models;

use App\Domain\Training\Enums\QuestionType;
use App\Support\Ids\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * A quiz question (D102). Options keep their authored order in both languages; each has a
 * stable id so answers survive edits to the wording.
 *
 * @property int $id
 * @property string $ulid
 * @property int $course_module_id
 * @property int $position
 * @property QuestionType $type
 * @property array<string, string>|string $prompt
 * @property list<array{id: string, label: array{en: string, ar: string}, correct: bool}> $options
 * @property int $points
 */
#[Guarded(['id', 'ulid'])]
final class QuizQuestion extends Model
{
    use HasPublicUlid;
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['prompt'];

    /**
     * @return BelongsTo<CourseModule, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'course_module_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'options' => 'array',
        ];
    }
}
