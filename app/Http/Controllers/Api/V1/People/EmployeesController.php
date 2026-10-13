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

        $page = $query->visibleTo($viewer)
            ->cursorPaginate($request->integer('per_page', 25))
            ->withQueryString();

        return EmployeeResource::collection($page);
    }

    public function show(Employee $employee): EmployeeResource
    {
        Gate::authorize('view', $employee);

        $employee->load(['currentBranch', 'currentDepartment', 'currentPosition', 'currentManager']);

        return new EmployeeResource($employee);
    }
}
