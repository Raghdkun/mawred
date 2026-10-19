<?php

declare(strict_types=1);

namespace App\Domain\Training\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Training\Data\CourseInputData;
use App\Domain\Training\Enums\CourseCategory;
use App\Domain\Training\Enums\CourseStatus;
use App\Domain\Training\Jobs\ApplyCourseAudienceJob;
use App\Domain\Training\Models\Course;
use App\Domain\Training\Support\CourseAudience;
use App\Domain\Training\Support\CourseCodes;
use App\Support\Actors\Actor;
use Illuminate\Database\ConnectionInterface;

/**
 * Creates or edits a course's details (D98–D100, D103). The audience is checked against the
 * organisation; changing it on a published course re-runs the assignment for that course.
 */
final readonly class SaveCourse
{
    public function __construct(private ConnectionInterface $db, private CourseCodes $codes) {}

    public function handle(Actor $actor, CourseInputData $input, ?Course $course = null): Course
    {
        $audience = $input->audience === null ? null : new CourseAudience(
            $input->audience['branches'] ?? [],
            $input->audience['departments'] ?? [],
            $input->audience['positions'] ?? [],
            $input->audience['employmentTypes'] ?? [],
            $input->audience['hiredWithinDays'] ?? null,
        );
        $audience?->assertValid();

        return $this->db->transaction(function () use ($actor, $input, $course, $audience): Course {
            $course ??= new Course(['code' => $this->codes->next(), 'created_by' => $actor->user?->id, 'status' => CourseStatus::Draft]);
            $both = fn (?array $text): ?array => $text === null || ($text['en'] ?? '') === '' ? null : ['en' => $text['en'], 'ar' => ($text['ar'] ?? null) ?: $text['en']];
            $owner = $input->ownerId === null ? null : User::query()->where('ulid', $input->ownerId)->value('id');

            $course->fill([
                'title' => $both($input->title),
                'summary' => $both($input->summary),
                'description' => $both($input->description),
                'category' => CourseCategory::from($input->category),
                'duration_minutes' => $input->durationMinutes,
                'requires_approval' => $input->requiresApproval,
                'issues_certificate' => $input->issuesCertificate,
                'validity_months' => $input->issuesCertificate ? $input->validityMonths : null,
                'owner_user_id' => $owner ?? $course->owner_user_id ?? $actor->user?->id,
                'audience' => $audience?->toArray(),
                'due_days' => $audience instanceof CourseAudience ? $input->dueDays : null,
            ]);
            $audienceChanged = $course->isDirty(['audience', 'due_days']);
            $course->save();

            activity('training')->causedBy($actor->user)->performedOn($course)->event($course->wasRecentlyCreated ? 'created' : 'updated')->log('Course saved');

            if ($audienceChanged && $course->status === CourseStatus::Published && $course->audience !== null) {
                dispatch(new ApplyCourseAudienceJob($course->id))->afterCommit();
            }

            return $course;
        });
    }
}
