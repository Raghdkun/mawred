<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Organization\Enums\CatalogKind;
use App\Domain\Organization\Support\OrganizationOptions;
use App\Domain\Policies\Actions\AssignPolicySet;
use App\Domain\Policies\Actions\EndPolicyAssignment;
use App\Domain\Policies\Data\PolicyAssignmentData;
use App\Domain\Policies\Enums\PolicyLevel;
use App\Domain\Policies\Models\PolicyAssignment;
use App\Domain\Policies\Models\PolicySet;
use App\Domain\Policies\Support\PolicyImpact;
use App\Domain\Policies\Support\PolicyTargets;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Assignments and the precedence chain (screen 18).
 */
final class PolicyAssignmentsController extends Controller
{
    public function index(PolicyTargets $targets): Response
    {
        Gate::authorize('policies.view');

        $today = CarbonImmutable::now('UTC')->toDateString();
        $assignments = PolicyAssignment::query()
            ->with('set')
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $today))
            ->orderBy('effective_from')
            ->get();

        return Inertia::render('policies/assignments', [
            'today' => $today,
            // General to specific; the UI draws grade and employment type in one card (D30).
            'levels' => array_map(fn (PolicyLevel $level): string => $level->value, PolicyLevel::cases()),
            'assignments' => array_values($assignments->map(fn (PolicyAssignment $a): PolicyAssignmentData => PolicyAssignmentData::fromAssignment($a, $targets))->all()),
            'sets' => PolicySet::query()->orderByRaw("name->>'en'")->get()->map(fn (PolicySet $set): array => ['id' => $set->ulid, 'name' => $set->getTranslations('name')]),
            'options' => [
                'grades' => OrganizationOptions::entries(CatalogKind::Grade),
                'employmentTypes' => OrganizationOptions::entries(CatalogKind::EmploymentType),
            ],
            'can' => ['update' => Gate::allows('policies.update')],
        ]);
    }

    public function store(Request $request, PolicyTargets $targets, AssignPolicySet $assign, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('policies.update');
        [$set, $level, $targetId] = $this->validated($request, $targets);

        if (! $set instanceof PolicySet) {
            throw new DomainRuleViolation('policies.set_required', __('Choose a policy set.'), ['setId' => [__('Choose a policy set.')]]);
        }

        $assign->handle(Actor::user($user), $set, $level, $targetId, $request->string('effectiveFrom')->toString(), $request->filled('reason') ? $request->string('reason')->toString() : null);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Policy set assigned.')]);

        return back();
    }

    public function preview(Request $request, PolicyTargets $targets, PolicyImpact $impact): JsonResponse
    {
        Gate::authorize('policies.view');
        [$set, $level, $targetId] = $this->validated($request, $targets, setRequired: false);

        return response()->json($impact->ofAssignment($level, $targetId, $set, $request->string('effectiveFrom')->toString()));
    }

    public function destroy(Request $request, PolicyAssignment $assignment, EndPolicyAssignment $end): RedirectResponse
    {
        Gate::authorize('policies.update');
        $request->validate(['endsOn' => ['required', 'date_format:Y-m-d']]);

        $end->handle($assignment, $request->string('endsOn')->toString());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assignment ended.')]);

        return back();
    }

    /**
     * @return array{0: PolicySet|null, 1: PolicyLevel, 2: int|null}
     */
    private function validated(Request $request, PolicyTargets $targets, bool $setRequired = true): array
    {
        $request->validate([
            'setId' => [$setRequired ? 'required' : 'nullable', 'string', 'exists:policy_sets,ulid'],
            'level' => ['required', Rule::enum(PolicyLevel::class)],
            'targetId' => ['nullable', 'string', 'max:26'],
            'effectiveFrom' => ['required', 'date_format:Y-m-d'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $level = PolicyLevel::from($request->string('level')->toString());
        $targetId = $targets->idFor($level, $request->filled('targetId') ? $request->string('targetId')->toString() : null);

        if ($level->needsTarget() && $targetId === null) {
            throw new DomainRuleViolation('policies.target_required', __('Choose who this applies to.'), ['targetId' => [__('Choose who this applies to.')]]);
        }

        $set = $request->filled('setId') ? PolicySet::query()->where('ulid', $request->string('setId')->toString())->first() : null;

        return [$set, $level, $targetId];
    }
}
