<?php

declare(strict_types=1);

namespace App\Domain\Training\Data;

use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Training\Models\Course;
use App\Domain\Training\Models\Enrollment;
use App\Domain\Training\Models\LearningPath;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A learning path and its courses in order, with the viewer's status on each (handoff 33 stepper). */
#[TypeScript]
final class LearningPathData extends Data
{
    /**
     * @param  list<array{id: string, code: string, title: string, durationMinutes: int|null, status: string|null, progress: int}>  $courses
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $description,
        public string $status,
        public array $courses,
        public bool $joined,
    ) {}

    /**
     * @param  array<int, Enrollment>  $mine  the viewer's latest enrolment per course id
     */
    public static function fromPath(LearningPath $path, array $mine, string $locale): self
    {
        $description = FieldSpec::text($path->getTranslations('description'), $locale);
        $courses = [];

        foreach ($path->courses as $course) {
            /** @var Course $course */
            $enrollment = $mine[$course->id] ?? null;
            $courses[] = [
                'id' => $course->ulid,
                'code' => $course->code,
                'title' => FieldSpec::text($course->getTranslations('title'), $locale),
                'durationMinutes' => $course->duration_minutes,
                'status' => $enrollment?->status->value,
                'progress' => $enrollment->progress ?? 0,
            ];
        }

        return new self(
            $path->ulid,
            FieldSpec::text($path->getTranslations('title'), $locale),
            $description === '' ? null : $description,
            $path->status->value,
            $courses,
            $courses !== [] && array_filter($courses, fn (array $c): bool => $c['status'] !== null) !== [],
        );
    }
}
