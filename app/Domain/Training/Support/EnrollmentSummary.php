<?php

declare(strict_types=1);

namespace App\Domain\Training\Support;

use App\Domain\Forms\Support\FieldSpec;
use App\Domain\People\Models\Employee;
use App\Domain\Training\Models\Enrollment;

/** What an enrolment request is about, for the approvals inbox (D99). */
final class EnrollmentSummary
{
    /**
     * @return array{about: string, facts: list<array{label: string, value: string}>, url: string|null}|null
     */
    public static function for(int $enrollmentId, string $locale): ?array
    {
        $enrollment = Enrollment::query()->with(['course', 'employee'])->find($enrollmentId);
        $course = $enrollment?->course;

        if ($enrollment === null || $course === null) {
            return null;
        }

        $title = FieldSpec::text($course->getTranslations('title'), $locale);
        $facts = [
            ['label' => __('Learner', [], $locale), 'value' => $enrollment->employee instanceof Employee ? $enrollment->employee->displayName($locale) : '—'],
            ['label' => __('Course', [], $locale), 'value' => "{$title} ({$course->code})"],
        ];

        if ($course->duration_minutes !== null) {
            $facts[] = ['label' => __('Length', [], $locale), 'value' => __(':count min', ['count' => $course->duration_minutes], $locale)];
        }

        return [
            'about' => __('Enrolment: :course', ['course' => $title], $locale),
            'facts' => $facts,
            'url' => route('training.courses.show', $course),
        ];
    }
}
