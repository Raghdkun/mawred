<?php

declare(strict_types=1);

namespace App\Domain\Training\Events;

use App\Domain\Training\Models\Enrollment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Someone finished every required part of a course. */
final readonly class EnrollmentCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Enrollment $enrollment) {}
}
