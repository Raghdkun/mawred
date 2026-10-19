<?php

declare(strict_types=1);

namespace App\Domain\Training\Listeners;

use App\Domain\Training\Actions\IssueCertificate;
use App\Domain\Training\Events\EnrollmentCompleted;

/** Completing a course that issues certificates issues one (D103). */
final readonly class IssueCertificates
{
    public function __construct(private IssueCertificate $issue) {}

    public function handle(EnrollmentCompleted $event): void
    {
        $this->issue->handle($event->enrollment);
    }
}
