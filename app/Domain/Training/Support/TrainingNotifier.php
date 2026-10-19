<?php

declare(strict_types=1);

namespace App\Domain\Training\Support;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Support\NotificationMessage;
use App\Domain\Notifications\Support\NotificationRouter;
use App\Domain\People\Models\Employee;
use App\Domain\Training\Models\Enrollment;

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
}
