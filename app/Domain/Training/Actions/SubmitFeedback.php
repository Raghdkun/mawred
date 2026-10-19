<?php

declare(strict_types=1);

namespace App\Domain\Training\Actions;

use App\Domain\Training\Enums\EnrollmentStatus;
use App\Domain\Training\Models\CourseFeedback;
use App\Domain\Training\Models\Enrollment;
use App\Support\Errors\DomainRuleViolation;

/** A learner rates a course they completed, 1–5, with an optional comment (D109); they can change it. */
final readonly class SubmitFeedback
{
    public function handle(Enrollment $enrollment, int $rating, ?string $comment): CourseFeedback
    {
        if ($enrollment->status !== EnrollmentStatus::Completed) {
            throw new DomainRuleViolation('training.feedback_not_completed', __('Finish the course before rating it.'));
        }

        return CourseFeedback::query()->updateOrCreate(['enrollment_id' => $enrollment->id], [
            'course_id' => $enrollment->course_id,
            'rating' => max(1, min(5, $rating)),
            'comment' => $comment === null || trim($comment) === '' ? null : mb_substr(trim($comment), 0, 2000),
        ]);
    }
}
