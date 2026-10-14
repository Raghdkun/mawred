<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Support\AnswerLabels;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Workflows\Actions\DecideTask;
use App\Domain\Workflows\Enums\TaskStatus;
use App\Domain\Workflows\Models\ActionToken;
use App\Domain\Workflows\Models\WorkflowTask;
use App\Http\Controllers\Controller;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Link approvals (ADR-0009, D24). Opening a link only shows a confirmation; nothing is decided
 * until the assignee confirms. Sensitive request types need the assignee signed in (and 2FA
 * when their role requires it, enforced by the auth middleware stack).
 */
final class ActionLinkController extends Controller
{
    public function show(Request $request, string $token): Response|RedirectResponse
    {
        [$link, $task, $subject] = $this->resolve($token);

        if (! $link instanceof ActionToken || ! $task instanceof WorkflowTask) {
            return Inertia::render('public/action-link', ['state' => 'expired']);
        }

        if ($this->needsSignIn($subject, $request, $task)) {
            return redirect()->guest(route('login'));
        }

        $version = $subject?->submission()->first()?->pinnedVersion();

        return Inertia::render('public/action-link', [
            'state' => 'ready',
            'token' => $token,
            'decision' => $link->decision->value,
            'request' => $subject instanceof EmployeeRequest ? [
                'number' => $subject->number,
                'type' => $subject->requestType()->getTranslations('name'),
                'requester' => $subject->requester?->displayName(app()->getLocale()),
                'submittedAt' => $subject->submitted_at->toIso8601String(),
            ] : null,
            // Sensitive answers are only shown after sign-in (D24).
            'schema' => $subject instanceof EmployeeRequest && ! $subject->requestType()->is_sensitive ? $version?->schema : null,
            'answers' => $subject instanceof EmployeeRequest && ! $subject->requestType()->is_sensitive ? $subject->submission()->first()?->data : null,
            'display' => $subject instanceof EmployeeRequest && $version !== null ? AnswerLabels::for($version->schema, $subject->submission()->firstOrFail()->data, app()->getLocale()) : [],
        ]);
    }

    public function confirm(Request $request, string $token, DecideTask $decide): Response|RedirectResponse
    {
        $request->validate(['comment' => ['nullable', 'string', 'max:2000']]);
        [$link, $task, $subject] = $this->resolve($token);

        if (! $link instanceof ActionToken || ! $task instanceof WorkflowTask) {
            return Inertia::render('public/action-link', ['state' => 'expired']);
        }

        if ($this->needsSignIn($subject, $request, $task)) {
            return redirect()->guest(route('login'));
        }

        $assignee = User::query()->findOrFail($task->assignee_user_id);

        try {
            $decide->handle($task, $assignee, $link->decision, $request->filled('comment') ? $request->string('comment')->toString() : null, $link->channel);
        } catch (DomainRuleViolation $e) {
            return back()->withErrors(['comment' => $e->getMessage()]);
        }

        $link->forceFill(['used_at' => now()])->save();

        return Inertia::render('public/action-link', ['state' => 'done', 'decision' => $link->decision->value]);
    }

    /**
     * @return array{0: ActionToken|null, 1: WorkflowTask|null, 2: EmployeeRequest|null}
     */
    private function resolve(string $token): array
    {
        $link = strlen($token) > 128 ? null : ActionToken::query()->where('token_hash', hash('sha256', $token))->first();

        if (! $link instanceof ActionToken || $link->used_at !== null || $link->expires_at->isPast()) {
            return [null, null, null];
        }

        $task = WorkflowTask::query()->find($link->workflow_task_id);

        if (! $task instanceof WorkflowTask || $task->status !== TaskStatus::Open) {
            return [null, null, null];
        }

        $instance = $task->owner();
        $subject = $instance->subject_type === EmployeeRequest::class ? EmployeeRequest::query()->with(['type', 'requester'])->find($instance->subject_id) : null;

        return [$link, $task, $subject];
    }

    /** Sensitive types (D24): the assignee must be the one signed in. */
    private function needsSignIn(?EmployeeRequest $subject, Request $request, WorkflowTask $task): bool
    {
        return $subject instanceof EmployeeRequest && $subject->requestType()->is_sensitive && $request->user()?->getKey() !== $task->assignee_user_id;
    }
}
