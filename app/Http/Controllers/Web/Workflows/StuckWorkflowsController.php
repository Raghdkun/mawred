<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Workflows;

use App\Domain\Access\Models\User;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Workflows\Actions\CancelWorkflowInstance;
use App\Domain\Workflows\Actions\RetryWorkflowStep;
use App\Domain\Workflows\Actions\SkipWorkflowStep;
use App\Domain\Workflows\Enums\InstanceStatus;
use App\Domain\Workflows\Enums\StepStatus;
use App\Domain\Workflows\Models\WorkflowInstance;
use App\Domain\Workflows\Models\WorkflowStep;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dead-letter view (ADR-0009): instances stopped on a step that failed all its retries, with
 * retry, skip and cancel.
 */
final class StuckWorkflowsController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('workflows.configure');

        $instances = WorkflowInstance::query()->with('definition')->where('status', InstanceStatus::Attention->value)->latest('updated_at')->paginate(25);
        $steps = WorkflowStep::query()->whereIn('workflow_instance_id', $instances->getCollection()->modelKeys())->where('status', StepStatus::Failed->value)->get()->keyBy('workflow_instance_id');
        $requests = EmployeeRequest::query()->whereIn('workflow_instance_id', $instances->getCollection()->modelKeys())->pluck('number', 'workflow_instance_id');

        return Inertia::render('workflows/stuck', [
            'instances' => $instances->through(fn (WorkflowInstance $instance): array => [
                'id' => $instance->ulid,
                'workflow' => $instance->definition?->getTranslations('name') ?? [],
                'request' => $requests[$instance->id] ?? null,
                'reason' => $instance->attention_reason,
                'since' => $instance->updated_at?->toIso8601String(),
                'step' => ($step = $steps[$instance->id] ?? null) instanceof WorkflowStep ? [
                    'id' => $step->ulid, 'node' => $step->node_id, 'type' => $step->node_type, 'attempts' => $step->attempts, 'error' => $step->error,
                ] : null,
            ]),
        ]);
    }

    public function retry(WorkflowStep $step, RetryWorkflowStep $retry, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('workflows.configure');
        $retry->handle(Actor::user($user), $step);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Retrying the step.')]);

        return back();
    }

    public function skip(WorkflowStep $step, SkipWorkflowStep $skip, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('workflows.configure');
        $skip->handle(Actor::user($user), $step);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Step skipped.')]);

        return back();
    }

    public function cancel(Request $request, WorkflowInstance $instance, CancelWorkflowInstance $cancel, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('workflows.configure');
        $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $cancel->handle(Actor::user($user), $instance, $request->string('reason')->toString());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workflow cancelled.')]);

        return back();
    }
}
