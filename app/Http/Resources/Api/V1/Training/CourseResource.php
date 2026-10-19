<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Training;

use App\Domain\Training\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A catalog course for the mobile app, in both languages. `modules` (the outline) is included
 * on show.
 *
 * @property-read Course $resource
 */
final class CourseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $course = $this->resource;

        return [
            'id' => $course->ulid,
            'code' => $course->code,
            'title' => $course->getTranslations('title'),
            'summary' => $course->getTranslations('summary'),
            'category' => $course->category->value,
            'duration_minutes' => $course->duration_minutes,
            'requires_approval' => $course->requires_approval,
            'mandatory' => $course->isMandatory(),
            'issues_certificate' => $course->issues_certificate,
            'validity_months' => $course->validity_months,
            'description' => $this->when($request->routeIs('api.v1.training.courses.show'), fn (): array => $course->getTranslations('description')),
            'modules' => $this->when($request->routeIs('api.v1.training.courses.show'), fn (): array => $course->modules()->get()->map(fn ($module): array => [
                'id' => $module->ulid,
                'kind' => $module->kind->value,
                'title' => $module->getTranslations('title'),
                'duration_minutes' => $module->duration_minutes,
                'required' => $module->required,
            ])->values()->all()),
        ];
    }
}
