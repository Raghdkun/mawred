<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Workflows;

use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\User;
use App\Domain\Forms\Models\FormVersion;
use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Forms\Support\FormContext;
use App\Domain\Forms\Support\FormSchema;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Models\RequestType;
use App\Domain\Workflows\Actions\DiscardWorkflowDraft;
use App\Domain\Workflows\Actions\PublishWorkflow;
use App\Domain\Workflows\Actions\SaveWorkflowDefinition;
use App\Domain\Workflows\Actions\SaveWorkflowDraft;
use App\Domain\Workflows\Data\WorkflowDefinitionData;
use App\Domain\Workflows\Engine\GraphValidator;
use App\Domain\Workflows\Engine\RecordUpdate;
use App\Domain\Workflows\Engine\RecordUpdates;
use App\Domain\Workflows\Engine\Simulator;
use App\Domain\Workflows\Enums\InstanceStatus;
use App\Domain\Workflows\Enums\VersionStatus;
use App\Domain\Workflows\Models\WebhookEndpoint;
use App\Domain\Workflows\Models\WorkflowDefinition;
use App\Domain\Workflows\Models\WorkflowVersion;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Workflows list and builder (handoff 15 ★): canvas, node settings, versions and the simulator.
 */
final class WorkflowsController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('workflows.view');

        return Inertia::render('workflows/index', [
            'workflows' => $this->rows(WorkflowDefinition::query()->orderBy('code')->get()),
            'can' => ['create' => Gate::allows('workflows.create')],
        ]);
    }

    public function store(Request $request, SaveWorkflowDefinition $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('workflows.create');
        $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z][A-Za-z0-9_-]*$/'],
            'name.en' => ['required', 'string', 'max:120'],
            'name.ar' => ['nullable', 'string', 'max:120'],
        ]);

        /** @var array<string, string> $name */
        $name = array_filter((array) $request->input('name'), is_string(...));
        $definition = $save->handle(Actor::user($user), $request->string('code')->toString(), $name);

        return to_route('workflows.builder', $definition);
    }

    public function builder(WorkflowDefinition $workflow, GraphValidator $validator, RecordUpdates $updates): Response
    {
        Gate::authorize('workflows.view');

        $draft = $workflow->draft()->first();
        $version = $draft ?? $workflow->publishedVersion()->first();
        $graph = $version instanceof WorkflowVersion ? $version->graph : ['format' => 'mawred.workflow/v1', 'nodes' => [], 'edges' => []];
        $fields = $this->formFields($workflow);

        return Inertia::render('workflows/builder', [
            'workflow' => $this->rows(new Collection([$workflow]))[0],
            'graph' => $graph,
            'isDraft' => $draft instanceof WorkflowVersion,
            'problems' => (object) $validator->problems($graph, $fields === null ? null : array_column($fields, 'key')),
            'versions' => WorkflowVersion::query()->where('workflow_definition_id', $workflow->id)->orderByDesc('number')->limit(20)->get()
                ->map(fn (WorkflowVersion $v): array => [
                    'number' => $v->number,
                    'status' => $v->status->value,
                    'publishedAt' => $v->published_at?->toIso8601String(),
                    'publishedBy' => $v->published_by === null ? null : User::query()->whereKey($v->published_by)->value('name'),
                ])->all(),
            'catalog' => [
                'fields' => $fields ?? [],
                'roles' => Role::query()->where('api_only', false)->orderBy('rank')->get()->map(fn (Role $role): array => ['name' => $role->name, 'label' => $role->getTranslations('label')])->all(),
                'recordUpdates' => array_map(fn (RecordUpdate $update): array => ['key' => $update->key(), 'label' => $update->label(), 'availableInPhase' => $update->availableInPhase()], array_values($updates->all())),
                'workflows' => WorkflowDefinition::query()->whereKeyNot($workflow->id)->whereNotNull('published_version_id')->orderBy('code')->get()
                    ->map(fn (WorkflowDefinition $d): array => ['code' => $d->code, 'name' => $d->getTranslations('name')])->all(),
                'webhooks' => WebhookEndpoint::query()->where('is_active', true)->orderBy('name')->get()->map(fn (WebhookEndpoint $w): array => ['id' => $w->ulid, 'name' => $w->name])->all(),
            ],
            'can' => ['update' => Gate::allows('workflows.update')],
        ]);
    }

    public function saveDraft(Request $request, WorkflowDefinition $workflow, SaveWorkflowDraft $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('workflows.update');
        $request->validate(['graph' => ['required', 'array']]);

        /** @var array<string, mixed> $graph */
        $graph = $request->input('graph');
        $save->handle(Actor::user($user), $workflow, $graph);

        return back();
    }

    public function publish(WorkflowDefinition $workflow, PublishWorkflow $publish, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('workflows.update');

        $fields = $this->formFields($workflow);
        $version = $publish->handle(Actor::user($user), $workflow, $fields === null ? null : array_column($fields, 'key'));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Version :number is live. Running requests keep the version they started on.', ['number' => $version->number])]);

        return back();
    }

    public function discard(WorkflowDefinition $workflow, DiscardWorkflowDraft $discard, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('workflows.update');

        $discard->handle(Actor::user($user), $workflow);

        return back();
    }

    /** Test-run the canvas as it is now (nothing is saved, sent or posted). */
    public function simulate(Request $request, WorkflowDefinition $workflow, Simulator $simulator, FormContext $context, #[CurrentUser] User $user): JsonResponse
    {
        Gate::authorize('workflows.view');
        $request->validate([
            'graph' => ['required', 'array'],
            'fields' => ['present', 'array'],
            'decisions' => ['present', 'array'],
            'decisions.*' => ['string', 'in:approved,rejected,done'],
        ]);

        $employee = Employee::query()->where('user_id', $user->id)->first();
        /** @var array<mixed> $graph */
        $graph = $request->input('graph');
        /** @var array<string, mixed> $fields */
        $fields = $request->input('fields');
        /** @var array<string, string> $decisions */
        $decisions = $request->input('decisions');

        return response()->json($simulator->run($graph, $fields, $decisions, $employee, $context->forEmployee($employee)));
    }

    /**
     * @param  Collection<int, WorkflowDefinition>  $definitions
     * @return list<WorkflowDefinitionData>
     */
    private function rows(Collection $definitions): array
    {
        $ids = $definitions->modelKeys();
        $published = WorkflowVersion::query()->whereIn('id', $definitions->pluck('published_version_id')->filter())->pluck('number', 'id');
        $drafts = WorkflowVersion::query()->whereIn('workflow_definition_id', $ids)->where('status', VersionStatus::Draft->value)->pluck('workflow_definition_id')->countBy();
        $open = WorkflowDefinition::query()->whereIn('workflow_definitions.id', $ids)
            ->join('workflow_instances', 'workflow_instances.workflow_definition_id', '=', 'workflow_definitions.id')
            ->whereIn('workflow_instances.status', [InstanceStatus::Running->value, InstanceStatus::Returned->value, InstanceStatus::Attention->value])
            ->groupBy('workflow_definitions.id')->selectRaw('workflow_definitions.id, count(*) as aggregate')->pluck('aggregate', 'id');

        $names = [];

        foreach (RequestType::query()->whereIn('workflow_definition_id', $ids)->get() as $type) {
            /** @var array<string, string> $name */
            $name = $type->getTranslations('name');
            $names[$type->workflow_definition_id][] = $name;
        }

        return array_values($definitions->map(fn (WorkflowDefinition $definition): WorkflowDefinitionData => WorkflowDefinitionData::fromDefinition(
            $definition,
            publishedVersion: $this->intOrNull($definition->published_version_id === null ? null : $published->get($definition->published_version_id)),
            hasDraft: $drafts->get($definition->id, 0) > 0,
            openInstances: $this->intOrNull($open->get($definition->id)) ?? 0,
            requestTypes: $names[$definition->id] ?? [],
        ))->all());
    }

    /**
     * Fields of the form bound through a request type, for conditions and field assignees.
     *
     * @return list<array{key: string, label: mixed, type: mixed, options: mixed}>|null
     */
    private function formFields(WorkflowDefinition $workflow): ?array
    {
        $type = RequestType::query()->where('workflow_definition_id', $workflow->id)->first();
        $versionId = $type?->form()->value('published_version_id');
        $version = $versionId === null ? null : FormVersion::query()->find($versionId);

        if (! $version instanceof FormVersion) {
            return null;
        }

        return array_values(array_map(fn (array $field): array => [
            'key' => FieldSpec::string($field['key'] ?? null),
            'label' => $field['label'] ?? null,
            'type' => $field['type'] ?? null,
            'options' => $field['options'] ?? [],
        ], FormSchema::fields($version->schema)));
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
