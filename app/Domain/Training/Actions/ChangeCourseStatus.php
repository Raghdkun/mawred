<?php

declare(strict_types=1);

namespace App\Domain\Training\Actions;

use App\Domain\Training\Enums\CourseStatus;
use App\Domain\Training\Enums\ModuleKind;
use App\Domain\Training\Jobs\ApplyCourseAudienceJob;
use App\Domain\Training\Models\Course;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

/**
 * Publishes a course to the catalog or archives it. A course needs at least one module, and
 * every quiz a question, before it goes live; publishing a mandatory course assigns it to its
 * audience. Archiving hides it from the catalog; people already on it can finish.
 */
final readonly class ChangeCourseStatus
{
    public function handle(Actor $actor, Course $course, CourseStatus $to): Course
    {
        if ($to === CourseStatus::Published) {
            if (! $course->modules()->exists()) {
                throw new DomainRuleViolation('training.no_modules', __('Add at least one module before publishing.'));
            }

            if ($course->modules()->where('kind', ModuleKind::Quiz->value)->doesntHave('questions')->exists()) {
                throw new DomainRuleViolation('training.empty_quiz', __('Every quiz needs at least one question.'));
            }
        }

        $from = $course->status;
        $course->forceFill([
            'status' => $to,
            'published_at' => $to === CourseStatus::Published ? ($course->published_at ?? now()) : $course->published_at,
        ])->save();

        activity('training')->causedBy($actor->user)->performedOn($course)->event('status')
            ->withProperties(['from' => $from->value, 'to' => $to->value])->log('Course status changed');

        if ($to === CourseStatus::Published && $course->audience !== null) {
            dispatch(new ApplyCourseAudienceJob($course->id));
        }

        return $course;
    }
}
