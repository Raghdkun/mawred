<?php

declare(strict_types=1);

namespace App\Domain\Training\Data;

use App\Domain\Forms\Support\FieldSpec;
use App\Domain\People\Models\Employee;
use App\Domain\Training\Models\Course;
use App\Domain\Training\Models\Enrollment;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** An enrolment: whose, on which course, how far along and by when (D99, D100). */
#[TypeScript]
final class EnrollmentData extends Data
{
    /**
     * @param  array{id: string, code: string, title: string}  $course
     * @param  array{id: string, name: string}|null  $employee
     */
    public function __construct(
        public string $id,
        public string $status,
        public string $source,
        public int $progress,
        public ?string $dueOn,
        public bool $overdue,
        public ?string $requestedAt,
        public ?string $enrolledAt,
        public ?string $completedAt,
        public array $course,
        public ?array $employee,
    ) {}

    public static function fromEnrollment(Enrollment $enrollment, string $locale, string $today, bool $withEmployee = false): self
    {
        $course = $enrollment->relationLoaded('course') ? $enrollment->course : $enrollment->course()->first();
        $employee = $withEmployee ? ($enrollment->relationLoaded('employee') ? $enrollment->employee : $enrollment->employee()->first()) : null;

        return new self(
            $enrollment->ulid,
            $enrollment->status->value,
            $enrollment->source->value,
            $enrollment->progress,
            $enrollment->due_on?->toDateString(),
            $enrollment->isOverdue($today),
            $enrollment->requested_at?->toIso8601String(),
            $enrollment->enrolled_at?->toIso8601String(),
            $enrollment->completed_at?->toIso8601String(),
            $course instanceof Course
                ? ['id' => $course->ulid, 'code' => $course->code, 'title' => FieldSpec::text($course->getTranslations('title'), $locale)]
                : ['id' => '', 'code' => '', 'title' => '—'],
            $employee instanceof Employee ? ['id' => $employee->ulid, 'name' => $employee->displayName($locale)] : null,
        );
    }
}
