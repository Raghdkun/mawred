<?php

declare(strict_types=1);

namespace App\Domain\Training\Actions;

use App\Domain\Training\Enums\EnrollmentStatus;
use App\Domain\Training\Events\EnrollmentCompleted;
use App\Domain\Training\Models\CourseModule;
use App\Domain\Training\Models\Enrollment;
use App\Domain\Training\Models\ModuleProgress;
use App\Domain\Training\Support\TrainingNotifier;

/**
 * Recomputes an enrolment's progress: the share of required modules done. When all are done
 * the course is complete (D98) and the learner hears; the certificate follows from the event.
 */
final readonly class RefreshEnrollmentProgress
{
    public function __construct(private TrainingNotifier $notifier) {}

    public function handle(Enrollment $enrollment): Enrollment
    {
        $required = CourseModule::query()->where('course_id', $enrollment->course_id)->where('required', true)->pluck('id');
        $done = $required->isEmpty() ? 0 : ModuleProgress::query()->where('enrollment_id', $enrollment->id)->whereIn('course_module_id', $required)->count();
        $progress = $required->isEmpty() ? 0 : intdiv($done * 100, $required->count());
        $completes = $enrollment->status === EnrollmentStatus::Enrolled && $required->isNotEmpty() && $done === $required->count();

        $enrollment->forceFill($completes
            ? ['progress' => 100, 'status' => EnrollmentStatus::Completed, 'completed_at' => now()]
            : ['progress' => $enrollment->status === EnrollmentStatus::Completed ? 100 : $progress])->save();

        if ($completes) {
            activity('training')->performedOn($enrollment)->event('completed')->log('Course completed');
            event(new EnrollmentCompleted($enrollment));
            $this->notifier->enrollment($enrollment, 'completed');
        }

        return $enrollment;
    }
}
