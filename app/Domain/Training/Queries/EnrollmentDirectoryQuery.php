<?php

declare(strict_types=1);

namespace App\Domain\Training\Queries;

use App\Domain\Access\Queries\ScopedQuery;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Training\Enums\EnrollmentStatus;
use App\Domain\Training\Models\Enrollment;
use App\Support\Search\SearchText;
use Illuminate\Database\Eloquent\Builder;

/**
 * Enrolments the viewer may see (`training.view` over the learner): trainers and HR across the
 * company, managers their branch or department. Filters by course, status, overdue (due date
 * before the given day) and the learner's name; newest first.
 *
 * @extends ScopedQuery<Enrollment>
 */
final class EnrollmentDirectoryQuery extends ScopedQuery
{
    private ?int $courseId = null;

    private ?EnrollmentStatus $status = null;

    private ?string $overdueBefore = null;

    private string $search = '';

    public function course(?int $courseId): self
    {
        $this->courseId = $courseId;

        return $this;
    }

    public function status(?EnrollmentStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    /** Enrolled, with a due date before this day (Y-m-d). */
    public function overdue(?string $before): self
    {
        $this->overdueBefore = $before;

        return $this;
    }

    public function search(string $search): self
    {
        $this->search = trim($search);

        return $this;
    }

    protected function permission(): string
    {
        return 'training.view';
    }

    protected function subject(): SubjectBinding
    {
        return SubjectBinding::employee('enrollments.employee_id');
    }

    protected function baseQuery(): Builder
    {
        return Enrollment::query()
            ->select('enrollments.*')
            ->join('employees', 'employees.id', '=', 'enrollments.employee_id')
            ->when($this->courseId, fn (Builder $q, int $id) => $q->where('enrollments.course_id', $id))
            ->when($this->status, fn (Builder $q, EnrollmentStatus $status) => $q->where('enrollments.status', $status->value))
            ->when($this->overdueBefore, fn (Builder $q, string $day) => $q->where('enrollments.status', EnrollmentStatus::Enrolled->value)->where('enrollments.due_on', '<', $day))
            ->when($this->search, fn (Builder $q, string $term) => $q->where('employees.search_text', 'like', SearchText::likePattern($term)))
            ->latest('enrollments.created_at')
            ->orderByDesc('enrollments.id');
    }
}
