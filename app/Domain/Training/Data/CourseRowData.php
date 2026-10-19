<?php

declare(strict_types=1);

namespace App\Domain\Training\Data;

use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Training\Models\Course;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A course in the trainers' list, with how many people are on it. */
#[TypeScript]
final class CourseRowData extends Data
{
    public function __construct(
        public string $id,
        public string $code,
        public string $title,
        public string $category,
        public string $status,
        public bool $mandatory,
        public bool $requiresApproval,
        public int $modules,
        public int $active,
        public int $requested,
        public int $completed,
    ) {}

    /** Expects `modules_count`, `active_count`, `requested_count` and `completed_count`. */
    public static function fromCourse(Course $course, string $locale): self
    {
        $count = function (string $attribute) use ($course): int {
            $value = $course->getAttribute($attribute);

            return is_int($value) ? $value : 0;
        };

        return new self(
            $course->ulid,
            $course->code,
            FieldSpec::text($course->getTranslations('title'), $locale),
            $course->category->value,
            $course->status->value,
            $course->isMandatory(),
            $course->requires_approval,
            $count('modules_count'),
            $count('active_count'),
            $count('requested_count'),
            $count('completed_count'),
        );
    }
}
