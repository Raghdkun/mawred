<?php

declare(strict_types=1);

namespace App\Domain\Training\Actions;

use App\Domain\Training\Enums\CourseStatus;
use App\Domain\Training\Enums\EnrollmentSource;
use App\Domain\Training\Models\Certificate;
use App\Domain\Training\Models\Course;
use App\Domain\Training\Support\LearnerClock;
use App\Domain\Training\Support\TrainingNotifier;
use App\Support\Actors\Actor;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Daily (D103): reminds holders and their managers 60, 30 and 7 days before a certificate
 * expires, each once, counted on the holder's branch-local date; and from 30 days out enrols them
 * again on the course to renew (due on the expiry date). Safe to re-run.
 */
final readonly class SendCertificateReminders
{
    public const array THRESHOLDS = [60, 30, 7];

    public const int RENEW_AT = 30;

    public function __construct(private LearnerClock $clock, private TrainingNotifier $notifier, private Enrol $enrol) {}

    public function handle(): int
    {
        $sent = 0;
        $horizon = CarbonImmutable::now()->addDays(max(self::THRESHOLDS) + 2)->toDateString();

        Certificate::query()->with('employee')->whereNull('revoked_at')->whereNotNull('expires_on')->where('expires_on', '<=', $horizon)
            ->orderBy('id')->each(function (Certificate $certificate) use (&$sent): void {
                $employee = $certificate->employee;
                $expires = $certificate->expires_on;

                if ($employee === null || $expires === null) {
                    return;
                }

                $daysLeft = (int) CarbonImmutable::parse($this->clock->today($employee))->diffInDays($expires, false);

                if ($daysLeft < 0) {
                    return;
                }

                $due = array_values(array_filter(self::THRESHOLDS, fn (int $t): bool => $daysLeft <= $t && ! in_array($t, $certificate->reminders_sent, true)));

                if ($due !== []) {
                    $this->notifier->certificate($certificate, 'expiring', $daysLeft);
                    $certificate->forceFill(['reminders_sent' => array_values(array_unique([...$certificate->reminders_sent, ...$due]))])->save();
                    $sent++;
                }

                if ($daysLeft <= self::RENEW_AT && $certificate->renewal_enrollment_id === null) {
                    $this->renew($certificate);
                }
            });

        return $sent;
    }

    private function renew(Certificate $certificate): void
    {
        $course = $certificate->course_id === null ? null : Course::query()->find($certificate->course_id);
        $employee = $certificate->employee;

        if (! $course instanceof Course || $course->status !== CourseStatus::Published || $employee === null) {
            return;
        }

        try {
            $enrollment = $this->enrol->handle(Actor::system('training:renewal'), $employee, $course, EnrollmentSource::Renewal, $certificate->expires_on?->toDateString());
            $certificate->forceFill(['renewal_enrollment_id' => $enrollment->id])->save();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
