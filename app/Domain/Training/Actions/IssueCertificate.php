<?php

declare(strict_types=1);

namespace App\Domain\Training\Actions;

use App\Domain\People\Models\Employee;
use App\Domain\Training\Models\Certificate;
use App\Domain\Training\Models\Enrollment;
use App\Domain\Training\Support\CertificateSerials;
use App\Domain\Training\Support\LearnerClock;
use App\Domain\Training\Support\TrainingNotifier;
use Carbon\CarbonImmutable;

/**
 * Issues the certificate for a completed course (D103, D110): the holder's name and the course
 * title as they are today, issued on the learner's branch-local date and valid for the course's
 * months. A renewal's new certificate replaces the reminder trail of the old one.
 */
final readonly class IssueCertificate
{
    public function __construct(private LearnerClock $clock, private TrainingNotifier $notifier) {}

    public function handle(Enrollment $enrollment): ?Certificate
    {
        $course = $enrollment->course()->firstOrFail();
        $employee = $enrollment->employee()->firstOrFail();

        if (! $course->issues_certificate) {
            return null;
        }

        $existing = Certificate::query()->where('enrollment_id', $enrollment->id)->first();

        if ($existing instanceof Certificate) {
            return $existing;
        }

        $issued = CarbonImmutable::parse($this->clock->today($employee));
        $certificate = Certificate::query()->create([
            'serial' => CertificateSerials::next($issued->year),
            'employee_id' => $employee->id,
            'course_id' => $course->id,
            'enrollment_id' => $enrollment->id,
            'title' => $course->getTranslations('title'),
            'holder' => ['en' => $employee->displayName('en'), 'ar' => $employee->displayName('ar')],
            'locale' => $this->locale($employee),
            'issued_on' => $issued->toDateString(),
            'expires_on' => $course->validity_months === null ? null : $issued->addMonthsNoOverflow($course->validity_months)->toDateString(),
        ]);

        activity('training')->performedOn($certificate)->event('issued')->withProperties(['serial' => $certificate->serial])->log('Certificate issued');
        $this->notifier->certificate($certificate, 'issued');

        return $certificate;
    }

    private function locale(Employee $employee): string
    {
        $locale = $employee->user_id === null ? null : $employee->user()->value('locale');

        return $locale === 'ar' ? 'ar' : 'en';
    }
}
