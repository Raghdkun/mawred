<?php

declare(strict_types=1);

namespace App\Domain\Training\Actions;

use App\Domain\Access\Models\User;
use App\Domain\People\Models\Employee;
use App\Domain\Training\Enums\BookingStatus;
use App\Domain\Training\Enums\EnrollmentStatus;
use App\Domain\Training\Enums\SessionStatus;
use App\Domain\Training\Models\Enrollment;
use App\Domain\Training\Models\SessionBooking;
use App\Domain\Training\Models\TrainingSession;
use App\Domain\Training\Support\CheckInCodes;
use App\Support\Errors\DomainRuleViolation;
use Carbon\CarbonImmutable;

/**
 * A learner checks in by scanning the trainer's rotating QR (D101). Anyone on the course may
 * check in, booked or not (a walk-in takes a seat if there is one). Only from an hour before the
 * start until the session ends.
 */
final readonly class CheckIn
{
    public function __construct(private RefreshEnrollmentProgress $refresh) {}

    public function handle(User $user, string $code, CarbonImmutable $now): SessionBooking
    {
        $session = CheckInCodes::session($code, $now->getTimestamp());

        if (! $session instanceof TrainingSession || $session->status !== SessionStatus::Scheduled) {
            throw new DomainRuleViolation('training.checkin_code', __('This code has expired. Scan the one on screen now.'));
        }

        if ($now->lt($session->starts_at->subHour()) || $now->gt($session->ends_at)) {
            throw new DomainRuleViolation('training.checkin_window', __('Check-in opens an hour before the session and closes when it ends.'));
        }

        $employee = Employee::query()->where('user_id', $user->id)->first();
        $enrollment = $employee instanceof Employee
            ? Enrollment::query()->where('employee_id', $employee->id)->where('course_id', $session->course_id)->whereIn('status', [EnrollmentStatus::Enrolled->value, EnrollmentStatus::Completed->value])->latest('id')->first()
            : null;

        if (! $enrollment instanceof Enrollment) {
            throw new DomainRuleViolation('training.checkin_not_enrolled', __('You aren’t enrolled in this course. Ask the trainer to add you.'));
        }

        $booking = SessionBooking::query()->firstOrNew(['training_session_id' => $session->id, 'enrollment_id' => $enrollment->id]);

        if ($booking->attended_at === null) {
            $booking->forceFill([
                'status' => BookingStatus::Booked,
                'booked_at' => $booking->booked_at ?? $now,
                'attended_at' => $now,
                'check_in' => 'qr',
            ])->save();

            activity('training')->causedBy($user)->performedOn($enrollment)->event('checked_in')->withProperties(['session' => $session->ulid])->log('Checked in');
            $this->refresh->handle($enrollment);
        }

        return $booking;
    }
}
