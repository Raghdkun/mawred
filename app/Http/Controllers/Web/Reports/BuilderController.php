<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Reports;

use App\Domain\Access\Models\User;
use App\Domain\Reports\Actions\DeleteCustomReport;
use App\Domain\Reports\Actions\SaveCustomReport;
use App\Domain\Reports\Actions\ShareCustomReport;
use App\Domain\Reports\Data\CustomReportInputData;
use App\Domain\Reports\Data\DatasetData;
use App\Domain\Reports\Data\SavedReportData;
use App\Domain\Reports\Datasets\BaseDataset;
use App\Domain\Reports\Datasets\DatasetRegistry;
use App\Domain\Reports\Models\SavedReport;
use App\Domain\Reports\Support\CustomReport;
use App\Domain\Reports\Support\CustomReportRunner;
use App\Domain\Reports\Support\DefinitionValidator;
use App\Domain\Reports\Support\ParamResolver;
use App\Domain\Reports\Support\ReportPreview;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The custom report builder (handoff 37, D118, D121): dataset → fields → filters → group →
 * visualize, with a live preview, saving, sharing (D119) and deleting.
 */
final class BuilderController extends Controller
{
    public function create(DatasetRegistry $datasets, #[CurrentUser] User $user): Response
    {
        Gate::authorize('create', SavedReport::class);

        return $this->page($datasets, $user, null);
    }

    public function edit(SavedReport $saved, DatasetRegistry $datasets, #[CurrentUser] User $user): Response
    {
        Gate::authorize('update', $saved);

        return $this->page($datasets, $user, $saved);
    }

    public function preview(Request $request, DatasetRegistry $datasets, DefinitionValidator $validator, ParamResolver $resolver, #[CurrentUser] User $user): JsonResponse
    {
        Gate::authorize('create', SavedReport::class);
        $request->validate(['definition' => ['required', 'array']]);
        /** @var array<string, mixed> $input */
        $input = $request->input('definition');
        $definition = $validator->validate($input, $user);
        /** @var BaseDataset<covariant Model> $dataset */
        $dataset = $datasets->find($definition->dataset);
        $draft = new SavedReport(['name' => '', 'dataset' => $definition->dataset, 'definition' => $definition->toArray()]);
        $report = new CustomReport($draft, $dataset, $definition, resolve(CustomReportRunner::class));

        return response()->json(ReportPreview::build($report, $user, $resolver->resolve($report, []), app()->getLocale()));
    }

    public function store(CustomReportInputData $input, SaveCustomReport $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('create', SavedReport::class);
        $saved = $save->handle(Actor::user($user), $input);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Report saved.')]);

        return to_route('reports.show', $saved->reportKey());
    }

    public function update(CustomReportInputData $input, SavedReport $saved, SaveCustomReport $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('update', $saved);
        $save->handle(Actor::user($user), $input, $saved);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Report saved.')]);

        return to_route('reports.show', $saved->reportKey());
    }

    public function share(Request $request, SavedReport $saved, ShareCustomReport $share, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('share', $saved);
        $request->validate([
            'people' => ['present', 'array', 'max:100'], 'people.*' => ['string', 'size:26'],
            'roles' => ['present', 'array', 'max:20'], 'roles.*' => ['string', 'max:60'],
        ]);
        /** @var list<string> $people */
        $people = array_values($request->array('people'));
        /** @var list<string> $roles */
        $roles = array_values($request->array('roles'));
        $share->handle(Actor::user($user), $saved, $people, $roles);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sharing updated.')]);

        return back();
    }

    public function destroy(SavedReport $saved, DeleteCustomReport $delete, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('delete', $saved);
        $delete->handle(Actor::user($user), $saved);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Report deleted.')]);

        return to_route('reports.index');
    }

    private function page(DatasetRegistry $datasets, User $user, ?SavedReport $saved): Response
    {
        $locale = app()->getLocale();

        return Inertia::render('reports/builder', [
            'datasets' => array_map(fn (BaseDataset $d): DatasetData => DatasetData::fromDataset($d, $user, $locale), $datasets->for($user)),
            'saved' => $saved instanceof SavedReport ? SavedReportData::fromSaved($saved, $user, $locale) : null,
        ]);
    }
}
