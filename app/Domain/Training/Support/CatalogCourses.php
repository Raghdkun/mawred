<?php

declare(strict_types=1);

namespace App\Domain\Training\Support;

use App\Domain\People\Models\Employee;
use App\Domain\Training\Enums\CourseCategory;
use App\Domain\Training\Enums\CourseStatus;
use App\Domain\Training\Models\Course;
use App\Domain\Training\Models\Enrollment;
use App\Support\Search\SearchText;
use Illuminate\Database\Eloquent\Builder;

/**
 * The catalog (handoff 33): published courses, open to everyone who may browse training, so a
 * deliberately unscoped lookup. Mandatory courses first, then newest.
 */
final class CatalogCourses
{
    /**
     * @return Builder<Course>
     */
    public function published(string $search = '', ?CourseCategory $category = null): Builder
    {
        $term = trim($search);

        return Course::query()->where('status', CourseStatus::Published->value)->withCount('modules')
            ->when($category, fn (Builder $q, CourseCategory $c) => $q->where('category', $c->value))
            ->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->whereRaw("lower(title->>'en') like ?", [SearchText::likePattern(mb_strtolower($term))])
                ->orWhereRaw("title->>'ar' like ?", [SearchText::likePattern($term)])
                ->orWhereRaw('lower(code) = ?', [mb_strtolower($term)])))
            ->orderByRaw('audience is null')
            ->latest('published_at')
            ->orderByDesc('id');
    }

    /**
     * The viewer's latest enrolment per course (live ones win).
     *
     * @return array<int, Enrollment>
     */
    public function latestEnrollments(?Employee $employee): array
    {
        if (! $employee instanceof Employee) {
            return [];
        }

        $latest = [];

        foreach (Enrollment::query()->where('employee_id', $employee->id)->orderByRaw("case when status in ('requested', 'enrolled') then 1 else 0 end")->oldest()->orderBy('id')->get() as $enrollment) {
            $latest[$enrollment->course_id] = $enrollment;
        }

        return $latest;
    }
}
