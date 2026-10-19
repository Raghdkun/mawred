<?php

declare(strict_types=1);

namespace App\Domain\Training\Actions;

use App\Domain\Training\Enums\EnrollmentStatus;
use App\Domain\Training\Enums\ModuleKind;
use App\Domain\Training\Models\CourseModule;
use App\Domain\Training\Models\Enrollment;
use App\Domain\Training\Models\ModuleProgress;
use App\Support\Errors\DomainRuleViolation;

/** The learner marks a lesson, video or file as done; quizzes complete only by passing them. */
final readonly class CompleteModule
{
    public function __construct(private RefreshEnrollmentProgress $refresh) {}

    public function handle(Enrollment $enrollment, CourseModule $module): Enrollment
    {
        if ($module->course_id !== $enrollment->course_id || $module->kind === ModuleKind::Quiz) {
            throw new DomainRuleViolation('training.module_mismatch', __('This module can’t be marked as done.'));
        }

        if ($enrollment->status === EnrollmentStatus::Completed) {
            return $enrollment;
        }

        if ($enrollment->status !== EnrollmentStatus::Enrolled) {
            throw new DomainRuleViolation('training.not_enrolled', __('Enrol in this course first.'));
        }

        ModuleProgress::query()->insertOrIgnore(['enrollment_id' => $enrollment->id, 'course_module_id' => $module->id, 'completed_at' => now()]);

        if ($enrollment->started_at === null) {
            $enrollment->forceFill(['started_at' => now()])->save();
        }

        return $this->refresh->handle($enrollment);
    }
}
