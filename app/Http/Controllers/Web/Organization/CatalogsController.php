<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Organization;

use App\Domain\Organization\Actions\DeleteCatalogEntry;
use App\Domain\Organization\Actions\SaveCatalogEntry;
use App\Domain\Organization\Data\CatalogEntryInputData;
use App\Domain\Organization\Enums\CatalogKind;
use App\Domain\Organization\Models\CostCenter;
use App\Domain\Organization\Models\EmploymentType;
use App\Domain\Organization\Models\Grade;
use App\Domain\Organization\Support\OrganizationOptions;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Organization → Types & grades (screen 39): employment types, grades and cost centres.
 */
final class CatalogsController extends Controller
{
    public function index(CatalogKind $kind): Response
    {
        Gate::authorize('organization.view');

        return Inertia::render('organization/catalogs/index', [
            'kind' => $kind->value,
            'kinds' => array_map(fn (CatalogKind $k): string => $k->value, CatalogKind::cases()),
            'entries' => OrganizationOptions::entries($kind, activeOnly: false),
            'can' => [
                'create' => Gate::allows('organization.create'),
                'update' => Gate::allows('organization.update'),
                'delete' => Gate::allows('organization.delete'),
            ],
        ]);
    }

    public function store(CatalogKind $kind, CatalogEntryInputData $data, SaveCatalogEntry $save): RedirectResponse
    {
        Gate::authorize('organization.create');

        $save->handle($kind, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved.')]);

        return back();
    }

    public function update(CatalogKind $kind, string $entry, CatalogEntryInputData $data, SaveCatalogEntry $save): RedirectResponse
    {
        Gate::authorize('organization.update');

        $save->handle($kind, $data, $this->find($kind, $entry));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saved.')]);

        return back();
    }

    public function destroy(CatalogKind $kind, string $entry, DeleteCatalogEntry $delete): RedirectResponse
    {
        Gate::authorize('organization.delete');

        $delete->handle($this->find($kind, $entry));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Archived.')]);

        return back();
    }

    private function find(CatalogKind $kind, string $ulid): EmploymentType|Grade|CostCenter
    {
        $model = $kind->modelClass();

        return $model::query()->where('ulid', $ulid)->firstOrFail();
    }
}
