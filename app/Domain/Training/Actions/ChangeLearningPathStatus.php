<?php

declare(strict_types=1);

namespace App\Domain\Training\Actions;

use App\Domain\Training\Enums\CourseStatus;
use App\Domain\Training\Models\LearningPath;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

/** Publishes or archives a learning path. Every course on it must be published first. */
final readonly class ChangeLearningPathStatus
{
    public function handle(Actor $actor, LearningPath $path, CourseStatus $to): LearningPath
    {
        if ($to === CourseStatus::Published && $path->courses()->where('courses.status', '!=', CourseStatus::Published->value)->exists()) {
            throw new DomainRuleViolation('training.path_unpublished_course', __('Publish every course on this path first.'));
        }

        $from = $path->status;
        $path->forceFill(['status' => $to])->save();

        activity('training')->causedBy($actor->user)->performedOn($path)->event('status')
            ->withProperties(['from' => $from->value, 'to' => $to->value])->log('Learning path status changed');

        return $path;
    }
}
