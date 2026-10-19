<?php

declare(strict_types=1);

namespace App\Domain\Training\Support;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Support\NotificationMessage;
use App\Domain\Notifications\Support\NotificationRouter;
use App\Domain\People\Models\Employee;
use App\Domain\Training\Models\Certificate;
use App\Domain\Training\Models\Enrollment;
use App\Domain\Training\Models\SessionBooking;
use Carbon\CarbonImmutable;

/** Training news for the learner (the Training notification category). */
final readonly class TrainingNotifier
{
    public function __construct(private NotificationRouter $router) {}

    /**
     * @param  'assigned'|'enrolled'|'approved'|'rejected'|'completed'  $what
     */
    public function enrollment(Enrollment $enrollment, string $what): void
    {
        $employee = Employee::query()->find($enrollment->employee_id);
        $user = $employee?->user_id === null ? null : User::query()->find($employee->user_id);

        if (! $user instanceof User) {
            return;
        }

        $course = $enrollment->course()->firstOrFail();
        $due = $enrollment->due_on?->toDateString();

        $this->router->send($user, new NotificationMessage(
            event: 'training.enrollment.'.$what,
            category: NotificationCategory::Training,
            content: function (string $channel, string $locale) use ($course, $what, $due, $enrollment): array {
                $title = FieldSpec::text($course->getTranslations('title'), $locale);

                return [
                    'subject' => FieldSpec::string(match ($what) {
                        'assigned' => __('New course to complete: :course', ['course' => $title], $locale),
                        'enrolled' => __('You’re enrolled: :course', ['course' => $title], $locale),
                        'approved' => __('Your enrolment was approved: :course', ['course' => $title], $locale),
                        'rejected' => __('Your enrolment wasn’t approved: :course', ['course' => $title], $locale),
                        'completed' => __('Course completed: :course', ['course' => $title], $locale),
                    }),
                    'body' => FieldSpec::string(match (true) {
                        $what === 'rejected' && $enrollment->decision_note !== null => $enrollment->decision_note,
                        $due !== null && in_array($what, ['assigned', 'enrolled', 'approved'], true) => __('Please complete it by :date.', ['date' => $due], $locale),
                        default => '',
                    }),
                ];
            },
            url: route('training.courses.show', $course),
            sourceKey: $enrollment->ulid.':'.$what,
        ));
    }

    /**
     * @param  'booked'|'waitlisted'|'promoted'  $what
     */
    public function booking(SessionBooking $booking, string $what): void
    {
        $session = $booking->session()->firstOrFail();
        $enrollment = $booking->enrollment()->firstOrFail();
        $user = $this->learner($enrollment);

        if (! $user instanceof User) {
            return;
        }

        $course = $session->course()->firstOrFail();

        $this->router->send($user, new NotificationMessage(
            event: 'training.session.'.$what,
            category: NotificationCategory::Training,
            content: function (string $channel, string $locale) use ($course, $session, $what): array {
                $title = FieldSpec::text($course->getTranslations('title'), $locale);
                $when = CarbonImmutable::parse($session->starts_at)->setTimezone($session->timezone)->settings(['locale' => $locale])->isoFormat('LLLL');

                return [
                    'subject' => FieldSpec::string(match ($what) {
                        'booked' => __('Seat booked: :course', ['course' => $title], $locale),
                        'waitlisted' => __('You’re on the waitlist: :course', ['course' => $title], $locale),
                        'promoted' => __('A seat opened up for you: :course', ['course' => $title], $locale),
                    }),
                    'body' => FieldSpec::string(__('Session on :when (:zone).', ['when' => $when, 'zone' => $session->timezone], $locale)),
                ];
            },
            url: route('training.courses.show', $course),
            sourceKey: $booking->ulid.':'.$what.':'.($booking->updated_at?->getTimestamp() ?? 0),
        ));
    }

    /**
     * @param  'issued'|'expiring'  $what
     */
    public function certificate(Certificate $certificate, string $what, int $daysLeft = 0): void
    {
        $employee = Employee::query()->find($certificate->employee_id);
        $recipients = [];

        foreach ([$employee?->user_id, $what === 'expiring' && $employee?->current_manager_id !== null ? Employee::query()->whereKey($employee->current_manager_id)->value('user_id') : null] as $userId) {
            $user = is_int($userId) ? User::query()->find($userId) : null;

            if ($user instanceof User) {
                $recipients[] = $user;
            }
        }

        foreach ($recipients as $index => $user) {
            $mine = $index === 0;
            $this->router->send($user, new NotificationMessage(
                event: 'training.certificate.'.$what,
                category: NotificationCategory::Training,
                content: function (string $channel, string $locale) use ($certificate, $what, $daysLeft, $mine): array {
                    $title = FieldSpec::text($certificate->getTranslations('title'), $locale);
                    $holder = FieldSpec::text($certificate->getTranslations('holder'), $locale);

                    return [
                        'subject' => FieldSpec::string(match (true) {
                            $what === 'issued' => __('Your certificate: :course', ['course' => $title], $locale),
                            $mine => __('Your certificate expires in :days days: :course', ['days' => $daysLeft, 'course' => $title], $locale),
                            default => __(':name’s certificate expires in :days days: :course', ['name' => $holder, 'days' => $daysLeft, 'course' => $title], $locale),
                        }),
                        'body' => FieldSpec::string($what === 'issued'
                            ? __('Serial :serial. Anyone can check it on the verification page.', ['serial' => $certificate->serial], $locale)
                            : __('A renewal enrolment is added 30 days before it expires.', [], $locale)),
                    ];
                },
                url: $mine ? route('training.my') : null,
                sourceKey: $certificate->ulid.':'.$what.':'.($what === 'expiring' ? $daysLeft : 0),
            ));
        }
    }

    private function learner(Enrollment $enrollment): ?User
    {
        $userId = Employee::query()->whereKey($enrollment->employee_id)->value('user_id');

        return is_int($userId) ? User::query()->find($userId) : null;
    }
}
