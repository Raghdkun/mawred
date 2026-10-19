<?php

declare(strict_types=1);

namespace App\Domain\Training\Data;

use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Training\Models\Course;
use App\Domain\Training\Models\Enrollment;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A course card in the catalog (handoff 33), with the viewer's own enrolment when they have one. */
#[TypeScript]
final class CourseCardData extends Data
{
    public function __construct(
        public string $id,
        public string $code,
        public string $title,
        public ?string $summary,
        public string $category,
        public ?int $durationMinutes,
        public int $modules,
        public bool $requiresApproval,
        public bool $mandatory,
        public bool $issuesCertificate,
        public ?EnrollmentData $enrollment,
    ) {}

    public static function fromCourse(Course $course, ?Enrollment $enrollment, string $locale, string $today): self
    {
        $summary = FieldSpec::text($course->getTranslations('summary'), $locale);
        $modules = $course->getAttribute('modules_count');

        return new self(
            $course->ulid,
            $course->code,
            FieldSpec::text($course->getTranslations('title'), $locale),
            $summary === '' ? null : $summary,
            $course->category->value,
            $course->duration_minutes,
            is_int($modules) ? $modules : 0,
            $course->requires_approval,
            $course->isMandatory(),
            $course->issues_certificate,
            $enrollment instanceof Enrollment ? EnrollmentData::fromEnrollment($enrollment, $locale, $today) : null,
        );
    }
}
