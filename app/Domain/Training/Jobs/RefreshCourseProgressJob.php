<?php

declare(strict_types=1);

namespace App\Domain\Training\Jobs;

use App\Domain\Training\Actions\RefreshEnrollmentProgress;
use App\Domain\Training\Enums\EnrollmentStatus;
use App\Domain\Training\Models\Enrollment;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Tries;

/** After a course's modules change, re-checks the progress of everyone on it. */
#[Queue('default')]
#[Tries(3)]
#[Backoff(60)]
final class RefreshCourseProgressJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $courseId) {}

    public function uniqueId(): string
    {
        return (string) $this->courseId;
    }

    public function handle(RefreshEnrollmentProgress $refresh): void
    {
        Enrollment::query()->where('course_id', $this->courseId)->where('status', EnrollmentStatus::Enrolled->value)
            ->each(fn (Enrollment $enrollment): Enrollment => $refresh->handle($enrollment));
    }
}
