<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Organization;

use App\Domain\Access\Models\User;
use App\Domain\Organization\Actions\DeletePosition;
use App\Domain\Organization\Actions\SavePosition;
use App\Domain\Organization\Data\PositionData;
use App\Domain\Organization\Data\PositionInputData;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Position;
use App\Domain\Organization\Queries\PositionDirectoryQuery;
use App\Domain\Organization\Support\OrganizationOptions;
use App\Http\Controllers\Controller;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Organization → Positions (screen 39).
 */
final class PositionsController extends Controller
{
    public function index(Request $request, PositionDirectoryQuery $query, #[CurrentUser] User $viewer): Response
    {
        Gate::authorize('organization.view');

        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'exists:departments,ulid'],
            'status' => ['nullable', 'in:active,all'],
        ]);

        $department = $request->filled('department')
            ? Department::query()->where('ulid', $request->string('department')->toString())->first()
            : null;

        $positions = $query->search($request->string('search')->toString())
            ->inDepartment($department)
            ->activeOnly($request->input('status', 'active') === 'active')
            ->visibleTo($viewer)
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Position $position): PositionData => PositionData::fromPosition($position));

        return Inertia::render('organization/positions/index', [
            'positions' => $positions,
            'filters' => [
                'search' => $request->input('search'),
                'department' => $request->input('department'),
                'status' => $request->input('status', 'active'),
            ],
            'options' => OrganizationOptions::grades(),
            'can' => [
                'create' => Gate::allows('organization.create'),
                'update' => Gate::allows('organization.update'),
                'delete' => Gate::allows('organization.delete'),
            ],
        ]);
    }

    public function store(PositionInputData $data, SavePosition $save): RedirectResponse
    {
        Gate::authorize('organization.create');

        $save->handle($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Position created.')]);

        return back();
    }

    public function update(PositionInputData $data, Position $position, SavePosition $save): RedirectResponse
    {
        Gate::authorize('organization.update');

        $save->handle($data, $position);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Position saved.')]);

        return back();
    }

    public function destroy(Position $position, DeletePosition $delete): RedirectResponse
    {
        Gate::authorize('organization.delete');

        $delete->handle($position);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Position archived.')]);

        return back();
    }
}
