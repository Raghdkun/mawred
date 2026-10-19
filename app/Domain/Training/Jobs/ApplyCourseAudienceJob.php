<?php

declare(strict_types=1);

namespace App\Domain\Training\Jobs;

use App\Domain\Training\Actions\ApplyCourseAudience;
use App\Domain\Training\Models\Course;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Tries;

/** Assigns a mandatory course to its audience (D100). Re-running skips people already on it. */
#[Queue('default')]
#[Tries(3)]
#[Backoff(60)]
final class ApplyCourseAudienceJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $courseId) {}

    public function uniqueId(): string
    {
        return (string) $this->courseId;
    }

    public function handle(ApplyCourseAudience $apply): void
    {
        $course = Course::query()->find($this->courseId);

        if ($course instanceof Course) {
            $apply->handle($course);
        }
    }
}
