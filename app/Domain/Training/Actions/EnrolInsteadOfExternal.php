<?php

declare(strict_types=1);

namespace App\Domain\Training\Actions;

use App\Domain\Training\Enums\EnrollmentSource;
use App\Domain\Training\Models\Course;
use App\Domain\Training\Models\Enrollment;
use App\Domain\Training\Models\ExternalTraining;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

/** HR meets a training request with a catalog course instead (D105): the person is enrolled and the record points at it. */
final readonly class EnrolInsteadOfExternal
{
    public function __construct(private Enrol $enrol) {}

    public function handle(Actor $actor, ExternalTraining $record, Course $course): Enrollment
    {
        if ($record->enrollment_id !== null) {
            throw new DomainRuleViolation('training.external_linked', __('This request already has a catalog course.'));
        }

        $enrollment = $this->enrol->handle($actor, $record->employee()->firstOrFail(), $course, EnrollmentSource::Request);
        $record->forceFill(['enrollment_id' => $enrollment->id])->save();

        activity('training')->causedBy($actor->user)->performedOn($record)->event('linked')->withProperties(['course' => $course->code])->log('External training met by a catalog course');

        return $enrollment;
    }
}
