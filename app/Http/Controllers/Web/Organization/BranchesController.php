<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Organization;

use App\Domain\Access\Models\User;
use App\Domain\Organization\Actions\DeleteBranch;
use App\Domain\Organization\Actions\SaveBranch;
use App\Domain\Organization\Data\BranchData;
use App\Domain\Organization\Data\BranchInputData;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Queries\BranchDirectoryQuery;
use App\Domain\Organization\Support\OrganizationOptions;
use App\Http\Controllers\Controller;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Organization → Branches (handoff screen 39): list, detail with geofence map, create/edit.
 */
final class BranchesController extends Controller
{
    public function index(Request $request, BranchDirectoryQuery $query, #[CurrentUser] User $viewer): Response
    {
        Gate::authorize('organization.view');

        $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:active,all']]);

        $branches = $query->search($request->string('search')->toString())
            ->activeOnly($request->input('status', 'active') === 'active')
            ->visibleTo($viewer)
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Branch $branch): BranchData => BranchData::fromBranch($branch));

        return Inertia::render('organization/branches/index', [
            'branches' => $branches,
            'filters' => ['search' => $request->input('search'), 'status' => $request->input('status', 'active')],
            'options' => OrganizationOptions::costCenters(),
            'can' => $this->abilities(),
        ]);
    }

    public function show(Branch $branch): Response
    {
        Gate::authorize('organization.view');

        $branch->load('costCenter:id,ulid');

        return Inertia::render('organization/branches/show', [
            'branch' => BranchData::fromBranch($branch),
            'options' => OrganizationOptions::costCenters(),
            'can' => $this->abilities(),
        ]);
    }

    public function store(BranchInputData $data, SaveBranch $save): RedirectResponse
    {
        Gate::authorize('organization.create');

        $branch = $save->handle($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Branch created.')]);

        return to_route('organization.branches.show', $branch);
    }

    public function update(BranchInputData $data, Branch $branch, SaveBranch $save): RedirectResponse
    {
        Gate::authorize('organization.update');

        $save->handle($data, $branch);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Branch saved.')]);

        return back();
    }

    public function destroy(Branch $branch, DeleteBranch $delete): RedirectResponse
    {
        Gate::authorize('organization.delete');

        $delete->handle($branch);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Branch archived.')]);

        return to_route('organization.branches.index');
    }

    /**
     * @return array{create: bool, update: bool, delete: bool}
     */
    private function abilities(): array
    {
        return [
            'create' => Gate::allows('organization.create'),
            'update' => Gate::allows('organization.update'),
            'delete' => Gate::allows('organization.delete'),
        ];
    }
}
