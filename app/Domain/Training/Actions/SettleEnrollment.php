<?php

declare(strict_types=1);

namespace App\Domain\Training\Actions;

use App\Domain\Training\Enums\EnrollmentStatus;
use App\Domain\Training\Models\Enrollment;
use App\Domain\Training\Support\TrainingNotifier;

/** The manager's decision on an enrolment request (D99): enrolled, or not, and the learner hears. */
final readonly class SettleEnrollment
{
    public function __construct(private TrainingNotifier $notifier) {}

    public function handle(Enrollment $enrollment, bool $approved): Enrollment
    {
        if ($enrollment->status !== EnrollmentStatus::Requested) {
            return $enrollment;
        }

        $enrollment->forceFill($approved
            ? ['status' => EnrollmentStatus::Enrolled, 'enrolled_at' => now()]
            : ['status' => EnrollmentStatus::Rejected])->save();

        activity('training')->performedOn($enrollment)->event($approved ? 'approved' : 'rejected')->log('Enrolment decided');

        $this->notifier->enrollment($enrollment, $approved ? 'approved' : 'rejected');

        return $enrollment;
    }
}
