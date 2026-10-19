<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Training;

use App\Domain\Training\Models\Course;
use App\Domain\Training\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An enrolment for the mobile app: the course, status, progress and due date.
 *
 * @property-read Enrollment $resource
 */
final class EnrollmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $enrollment = $this->resource;
        $course = $enrollment->course;

        return [
            'id' => $enrollment->ulid,
            'status' => $enrollment->status->value,
            'source' => $enrollment->source->value,
            'progress' => $enrollment->progress,
            'due_on' => $enrollment->due_on?->toDateString(),
            'requested_at' => $enrollment->requested_at?->toIso8601String(),
            'enrolled_at' => $enrollment->enrolled_at?->toIso8601String(),
            'completed_at' => $enrollment->completed_at?->toIso8601String(),
            'course' => $course instanceof Course ? ['id' => $course->ulid, 'code' => $course->code, 'title' => $course->getTranslations('title')] : null,
        ];
    }
}
