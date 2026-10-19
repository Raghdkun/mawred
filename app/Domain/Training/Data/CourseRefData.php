<?php

declare(strict_types=1);

namespace App\Domain\Training\Data;

use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Training\Models\Course;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A course named in a page header or a picker. */
#[TypeScript]
final class CourseRefData extends Data
{
    public function __construct(
        public string $id,
        public string $code,
        public string $title,
        public string $status,
        public bool $mandatory,
    ) {}

    public static function fromCourse(Course $course, string $locale): self
    {
        return new self($course->ulid, $course->code, FieldSpec::text($course->getTranslations('title'), $locale), $course->status->value, $course->isMandatory());
    }
}
