<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Organization\Models\Branch;
use App\Domain\Policies\Actions\DeletePolicySet;
use App\Domain\Policies\Actions\ExportPolicyPreset;
use App\Domain\Policies\Actions\ImportPolicyPreset;
use App\Domain\Policies\Actions\SavePolicySet;
use App\Domain\Policies\Actions\SetPolicyValues;
use App\Domain\Policies\Data\PolicyKeyData;
use App\Domain\Policies\Data\PolicySetData;
use App\Domain\Policies\Data\PolicySetValueData;
use App\Domain\Policies\Models\PolicySet;
use App\Domain\Policies\Models\PolicyValue;
use App\Domain\Policies\Support\PolicyImpact;
use App\Domain\Policies\Support\PolicyKeys;
use App\Domain\Policies\Support\PolicyTargets;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Policy sets (screen 16), the set editor (17) and its change-impact preview (20).
 */
final class PolicySetsController extends Controller
{
    public function index(Request $request, PolicyTargets $targets): Response
    {
        Gate::authorize('policies.view');
        $request->validate(['search' => ['nullable', 'string', 'max:100']]);

        $today = $this->today();
        $search = $request->string('search')->trim()->toString();

        $sets = PolicySet::query()
            ->when($search !== '', fn ($q) => $q->whereRaw('name::text ilike ?', ['%'.addcslashes($search, '%_\\').'%']))
            ->withCount(['values as keys_count' => fn (EloquentBuilder $q) => $this->inForce($q, $today)])
            ->withMax('values', 'effective_from')
            ->with(['assignments' => fn ($q) => $q->with('set')
                ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $today))
                ->orderBy('effective_from')])
            ->orderByRaw("preset_key is null, name->>'en'")
            ->paginate(25)
            ->withQueryString()
            ->through(fn (PolicySet $set): PolicySetData => PolicySetData::fromSet($set, $targets));

        return Inertia::render('policies/index', [
            'sets' => $sets,
            'filters' => ['search' => $request->input('search')],
            'can' => $this->can(),
        ]);
    }

    public function store(Request $request, SavePolicySet $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('policies.create');
        $this->validateName($request);

        $set = $save->handle(Actor::user($user), $this->name($request), $this->description($request));

        return to_route('policies.show', $set);
    }

    public function show(Request $request, PolicySet $set, PolicyKeys $keys): Response
    {
        Gate::authorize('policies.view');
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        $date = $request->string('date', $this->today())->toString();
        $rows = PolicyValue::query()
            ->where('policy_set_id', $set->id)
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
            ->orderBy('effective_from')
            ->get()
            ->groupBy('key');

        $values = [];

        foreach ($rows as $key => $history) {
            $current = $history->first(fn (PolicyValue $row): bool => $row->coversDate($date));
            $values[] = new PolicySetValueData(
                key: (string) $key,
                value: $current?->value,
                effectiveFrom: $current?->effective_from->toDateString(),
                scheduled: array_values($history
                    ->filter(fn (PolicyValue $row): bool => $row->effective_from->toDateString() > $date)
                    ->map(fn (PolicyValue $row): array => ['value' => $row->value, 'effectiveFrom' => $row->effective_from->toDateString()])
                    ->all()),
            );
        }

        $set->loadCount(['values as keys_count' => fn (EloquentBuilder $q) => $this->inForce($q, $date)])
            ->loadMax('values', 'effective_from')
            ->load(['assignments' => fn ($q) => $q->with('set')->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))]);

        return Inertia::render('policies/show', [
            'set' => PolicySetData::fromSet($set, resolve(PolicyTargets::class)),
            'date' => $date,
            'today' => $this->today(),
            'keys' => array_values(array_map(PolicyKeyData::fromKey(...), $keys->all())),
            'values' => $values,
            'weekStartsOn' => $this->weekStart(),
            'can' => $this->can(),
        ]);
    }

    public function update(Request $request, PolicySet $set, SavePolicySet $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('policies.update');
        $this->validateName($request);

        $save->handle(Actor::user($user), $this->name($request), $this->description($request), $set);

        return $this->saved(__('Policy set saved.'));
    }

    public function updateValues(Request $request, PolicySet $set, SetPolicyValues $setValues, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('policies.update');
        $this->validateChange($request);

        /** @var array<string, mixed> $values */
        $values = $request->input('values', []);
        $setValues->handle(Actor::user($user), $set, $values, $request->string('effectiveFrom')->toString(), $request->filled('reason') ? $request->string('reason')->toString() : null);

        return $this->saved(__('Policy values saved.'));
    }

    public function preview(Request $request, PolicySet $set, PolicyKeys $keys, PolicyImpact $impact): JsonResponse
    {
        Gate::authorize('policies.view');
        $this->validateChange($request);

        /** @var array<string, mixed> $values */
        $values = $request->input('values', []);

        return response()->json($impact->ofValues($set, $keys->validate($values), $request->string('effectiveFrom')->toString()));
    }

    public function destroy(PolicySet $set, DeletePolicySet $delete): RedirectResponse
    {
        Gate::authorize('policies.delete');

        $delete->handle($set);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Policy set archived.')]);

        return to_route('policies.index');
    }

    public function export(Request $request, PolicySet $set, ExportPolicyPreset $export): JsonResponse
    {
        Gate::authorize('policies.export');
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        $preset = $export->handle($set, $request->string('date', $this->today())->toString());
        $name = $set->getTranslation('name', 'en');
        $file = (is_string($name) ? Str::slug($name) : '') ?: 'policy-set';

        return response()->json($preset, 200, ['Content-Disposition' => "attachment; filename=\"{$file}.mawred-policy.json\""], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function import(Request $request, ImportPolicyPreset $import, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('policies.create');
        $request->validate([
            'file' => ['required', 'file', 'max:512', 'mimetypes:application/json,text/plain'],
            'effectiveFrom' => ['required', 'date_format:Y-m-d'],
        ]);

        $file = $request->file('file');
        $preset = $file instanceof UploadedFile ? json_decode((string) $file->get(), true) : null;
        $set = $import->handle(Actor::user($user), is_array($preset) ? $preset : [], $request->string('effectiveFrom')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Preset imported.')]);

        return to_route('policies.show', $set);
    }

    private function validateName(Request $request): void
    {
        $request->validate([
            'name.en' => ['required', 'string', 'max:120'],
            'name.ar' => ['nullable', 'string', 'max:120'],
            'description.en' => ['nullable', 'string', 'max:500'],
            'description.ar' => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function validateChange(Request $request): void
    {
        $request->validate([
            'values' => ['required', 'array', 'min:1'],
            'effectiveFrom' => ['required', 'date_format:Y-m-d'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
    }

    /**
     * @return array<string, string|null>
     */
    private function name(Request $request): array
    {
        return ['en' => $request->string('name.en')->trim()->toString(), 'ar' => $request->filled('name.ar') ? $request->string('name.ar')->trim()->toString() : null];
    }

    /**
     * @return array<string, string|null>
     */
    private function description(Request $request): array
    {
        return [
            'en' => $request->filled('description.en') ? $request->string('description.en')->trim()->toString() : null,
            'ar' => $request->filled('description.ar') ? $request->string('description.ar')->trim()->toString() : null,
        ];
    }

    private function inForce(EloquentBuilder $query, string $date): void
    {
        $query->where('effective_from', '<=', $date)->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date));
    }

    /** Weekday chips start the day after the first branch's weekend (handoff 17). */
    private function weekStart(): int
    {
        $days = Branch::query()->oldest('id')->first(['id', 'weekend_days'])->weekend_days ?? [6, 7];

        return $days === [] ? 1 : max($days) % 7 + 1;
    }

    private function today(): string
    {
        return CarbonImmutable::now('UTC')->toDateString();
    }

    /**
     * @return array<string, bool>
     */
    private function can(): array
    {
        return [
            'create' => Gate::allows('policies.create'),
            'update' => Gate::allows('policies.update'),
            'delete' => Gate::allows('policies.delete'),
            'export' => Gate::allows('policies.export'),
        ];
    }

    private function saved(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
