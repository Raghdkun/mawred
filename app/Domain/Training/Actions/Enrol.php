<?php

declare(strict_types=1);

namespace App\Domain\Training\Actions;

use App\Domain\People\Enums\EmployeeStatus;
use App\Domain\People\Models\Employee;
use App\Domain\Training\Enums\CourseStatus;
use App\Domain\Training\Enums\EnrollmentSource;
use App\Domain\Training\Enums\EnrollmentStatus;
use App\Domain\Training\Models\Course;
use App\Domain\Training\Models\Enrollment;
use App\Domain\Training\Models\LearningPath;
use App\Domain\Training\Support\TrainingNotifier;
use App\Domain\Workflows\Engine\WorkflowEngine;
use App\Domain\Workflows\Models\WorkflowDefinition;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Puts someone on a course. When they ask themselves (from the catalog or a learning path) and
 * the course needs approval, the enrolment waits for their manager through the
 * `TRAINING_ENROLMENT` workflow (D99); everything else enrols straight away. One live enrolment
 * per person and course: asking again returns it, and an assignment can bring its due date
 * forward (D100).
 */
final readonly class Enrol
{
    public const string WORKFLOW = 'TRAINING_ENROLMENT';

    public function __construct(
        private ConnectionInterface $db,
        private WorkflowEngine $engine,
        private TrainingNotifier $notifier,
    ) {}

    public function handle(Actor $actor, Employee $employee, Course $course, EnrollmentSource $source, ?string $dueOn = null, ?LearningPath $path = null): Enrollment
    {
        if ($course->status !== CourseStatus::Published) {
            throw new DomainRuleViolation('training.course_closed', __('This course isn’t open for enrolment.'));
        }

        if (! in_array($employee->status, [EmployeeStatus::Active, EmployeeStatus::Pending], true)) {
            throw new DomainRuleViolation('training.employee_inactive', __('Only current employees can be enrolled.'));
        }

        $live = $this->live($employee, $course);

        if ($live instanceof Enrollment) {
            if ($dueOn !== null && $source !== EnrollmentSource::SelfService && ($live->due_on === null || $live->due_on->toDateString() > $dueOn)) {
                $live->forceFill(['due_on' => $dueOn])->save();
            }

            return $live;
        }

        $needsApproval = $course->requires_approval && in_array($source, [EnrollmentSource::SelfService, EnrollmentSource::Path], true);
        $workflow = $needsApproval ? WorkflowDefinition::query()->where('code', self::WORKFLOW)->first() : null;

        if ($needsApproval && ($workflow === null || $workflow->published_version_id === null)) {
            throw new DomainRuleViolation('training.workflow_missing', __('The enrolment approval workflow isn’t published. Ask HR to publish it.'));
        }

        try {
            $enrollment = $this->db->transaction(function () use ($actor, $employee, $course, $source, $dueOn, $path, $workflow): Enrollment {
                $now = now();
                $enrollment = Enrollment::query()->create([
                    'employee_id' => $employee->id,
                    'course_id' => $course->id,
                    'learning_path_id' => $path?->id,
                    'status' => $workflow instanceof WorkflowDefinition ? EnrollmentStatus::Requested : EnrollmentStatus::Enrolled,
                    'source' => $source,
                    'assigned_by' => $source === EnrollmentSource::SelfService ? null : $actor->user?->id,
                    'due_on' => $dueOn,
                    'requested_at' => $workflow instanceof WorkflowDefinition ? $now : null,
                    'enrolled_at' => $workflow instanceof WorkflowDefinition ? null : $now,
                ]);

                if ($workflow instanceof WorkflowDefinition) {
                    $instance = $this->engine->start($workflow, [
                        'field' => ['course' => $course->code, 'due_on' => $dueOn],
                        'course' => ['code' => $course->code, 'title' => $course->getTranslations('title')],
                    ], $actor->user, $employee, $enrollment);
                    $enrollment->forceFill(['workflow_instance_id' => $instance->id])->save();
                }

                activity('training')->causedBy($actor->user)->performedOn($enrollment)->event($enrollment->status === EnrollmentStatus::Requested ? 'requested' : 'enrolled')
                    ->withProperties(['course' => $course->code, 'source' => $source->value])->log('Enrolment created');

                return $enrollment;
            });
        } catch (UniqueConstraintViolationException) {
            // Someone else enrolled them at the same moment: theirs stands.
            return $this->live($employee, $course) ?? throw new DomainRuleViolation('training.enrol_failed', __('Enrolment failed. Try again.'));
        }

        if ($enrollment->status === EnrollmentStatus::Enrolled) {
            $this->notifier->enrollment($enrollment, in_array($source, [EnrollmentSource::SelfService, EnrollmentSource::Path], true) ? 'enrolled' : 'assigned');
        }

        return $enrollment;
    }

    private function live(Employee $employee, Course $course): ?Enrollment
    {
        return Enrollment::query()->where('employee_id', $employee->id)->where('course_id', $course->id)
            ->whereIn('status', [EnrollmentStatus::Requested->value, EnrollmentStatus::Enrolled->value])->first();
    }
}
