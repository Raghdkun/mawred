<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\People;

use App\Domain\Access\Models\User;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Department;
use App\Domain\People\Enums\EmployeeStatus;
use App\Domain\People\Models\Employee;
use App\Domain\People\Queries\EmployeeDirectoryQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\People\EmployeeResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Employee directory for the mobile app and people pickers (scoped by people.view).
 */
final class EmployeesController extends Controller
{
    public function index(Request $request, EmployeeDirectoryQuery $query, #[CurrentUser] User $viewer): AnonymousResourceCollection
    {
        Gate::authorize('people.view');

        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'branch' => ['nullable', 'string', 'exists:branches,ulid'],
            'department' => ['nullable', 'string', 'exists:departments,ulid'],
            'status' => ['nullable', Rule::enum(EmployeeStatus::class)],
            'sort' => ['nullable', Rule::in(EmployeeDirectoryQuery::SORTS)],
            'manager' => ['nullable', 'string', 'exists:employees,ulid'],
            'roots' => ['nullable', 'boolean'],
            'with_reports_count' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $branchId = $request->filled('branch') ? Branch::query()->where('ulid', $request->string('branch')->toString())->value('id') : null;
        $department = $request->filled('department') ? Department::query()->where('ulid', $request->string('department')->toString())->first() : null;

        $query->search($request->string('search')->toString())
            ->inBranches(is_numeric($branchId) ? [(int) $branchId] : [])
            ->inDepartment($department)
            ->sortBy($request->string('sort', 'name')->toString());

        if ($request->filled('status')) {
            $query->withStatuses([EmployeeStatus::from($request->string('status')->toString())]);
        }

        $manager = $request->filled('manager') ? Employee::query()->where('ulid', $request->string('manager')->toString())->value('id') : null;
        $query->reportingTo(is_numeric($manager) ? (int) $manager : null);

        $builder = $query->visibleTo($viewer);
        $visibleIds = resolve(EmployeeDirectoryQuery::class)->visibleTo($viewer)->reorder()->select('employees.id')->toBase();

        if ($request->boolean('roots')) {
            // The top of what the viewer can see: people whose manager is absent or out of scope.
            $builder->where(fn (Builder $q) => $q->whereNull('employees.current_manager_id')->orWhereNotIn('employees.current_manager_id', $visibleIds));
        }

        if ($request->boolean('with_reports_count')) {
            $builder->withCount(['directReports as direct_reports_count' => fn (Builder $q) => $q->whereIn('id', clone $visibleIds)]);
        }

        $page = $builder->cursorPaginate($request->integer('per_page', 25))
            ->withQueryString();

        return EmployeeResource::collection($page);
    }

    public function show(Employee $employee): EmployeeResource
    {
        // Out-of-scope records answer 404, so the API never confirms they exist.
        abort_unless(Gate::allows('view', $employee), 404);

        $employee->load(['currentBranch', 'currentDepartment', 'currentPosition', 'currentManager']);

        return new EmployeeResource($employee);
    }
}
