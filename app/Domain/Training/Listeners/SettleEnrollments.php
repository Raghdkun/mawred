<?php

declare(strict_types=1);

namespace App\Domain\Training\Listeners;

use App\Domain\Training\Actions\SettleEnrollment;
use App\Domain\Training\Models\Enrollment;
use App\Domain\Workflows\Enums\Outcome;
use App\Domain\Workflows\Events\InstanceFinished;

/** Enrols or turns down a requested enrolment when its approval workflow ends (D99). */
final readonly class SettleEnrollments
{
    public function __construct(private SettleEnrollment $settle) {}

    public function handle(InstanceFinished $event): void
    {
        $instance = $event->instance;

        if ($instance->subject_type !== Enrollment::class || $instance->subject_id === null) {
            return;
        }

        $enrollment = Enrollment::query()->find($instance->subject_id);

        if ($enrollment instanceof Enrollment && in_array($instance->outcome, [Outcome::Approved, Outcome::Rejected], true)) {
            $this->settle->handle($enrollment, $instance->outcome === Outcome::Approved);
        }
    }
}
