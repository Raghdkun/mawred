<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Organization;

use App\Domain\Access\Models\User;
use App\Domain\Organization\Actions\DeleteDepartment;
use App\Domain\Organization\Actions\MoveDepartment;
use App\Domain\Organization\Actions\SaveDepartment;
use App\Domain\Organization\Data\DepartmentData;
use App\Domain\Organization\Data\DepartmentInputData;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Queries\DepartmentTreeQuery;
use App\Domain\Organization\Support\OrganizationOptions;
use App\Http\Controllers\Controller;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Organization → Departments (screen 39): the unlimited-depth tree with drag to reparent.
 * The whole tree ships at once; the client filters and collapses it.
 */
final class DepartmentsController extends Controller
{
    public function index(DepartmentTreeQuery $query, #[CurrentUser] User $viewer): Response
    {
        Gate::authorize('organization.view');

        $departments = $query->visibleTo($viewer)
            ->get()
            ->map(fn (Department $department): DepartmentData => DepartmentData::fromDepartment($department));

        return Inertia::render('organization/departments/index', [
            'departments' => $departments,
            'options' => OrganizationOptions::costCenters(),
            'can' => [
                'create' => Gate::allows('organization.create'),
                'update' => Gate::allows('organization.update'),
                'delete' => Gate::allows('organization.delete'),
            ],
        ]);
    }

    public function store(DepartmentInputData $data, SaveDepartment $save): RedirectResponse
    {
        Gate::authorize('organization.create');

        $save->handle($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department created.')]);

        return back();
    }

    public function update(DepartmentInputData $data, Department $department, SaveDepartment $save): RedirectResponse
    {
        Gate::authorize('organization.update');

        $save->handle($data, $department);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department saved.')]);

        return back();
    }

    public function move(Request $request, Department $department, MoveDepartment $move): RedirectResponse
    {
        Gate::authorize('organization.update');

        $request->validate(['parentId' => ['nullable', 'string', 'exists:departments,ulid']]);

        $parent = $request->filled('parentId')
            ? Department::query()->where('ulid', $request->string('parentId')->toString())->firstOrFail()
            : null;

        $move->handle($department, $parent);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department moved.')]);

        return back();
    }

    public function destroy(Department $department, DeleteDepartment $delete): RedirectResponse
    {
        Gate::authorize('organization.delete');

        $delete->handle($department);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Department archived.')]);

        return back();
    }
}
