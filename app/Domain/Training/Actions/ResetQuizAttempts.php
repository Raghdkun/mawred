<?php

declare(strict_types=1);

namespace App\Domain\Training\Actions;

use App\Domain\Training\Models\CourseModule;
use App\Domain\Training\Models\Enrollment;
use App\Domain\Training\Models\QuizAttempt;
use App\Support\Actors\Actor;

/** A trainer gives a learner who used every attempt another go at a quiz (D102). */
final readonly class ResetQuizAttempts
{
    public function handle(Actor $actor, Enrollment $enrollment, CourseModule $module): void
    {
        $removed = QuizAttempt::query()->where('enrollment_id', $enrollment->id)->where('course_module_id', $module->id)->where('passed', false)->delete();

        activity('training')->causedBy($actor->user)->performedOn($enrollment)->event('quiz_reset')
            ->withProperties(['module' => $module->ulid, 'attempts' => $removed])->log('Quiz attempts reset');
    }
}
