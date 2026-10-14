<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Support;

use App\Domain\Access\Models\User;
use App\Domain\People\Models\Employee;
use App\Domain\Requests\Models\EmployeeRequest;
use App\Domain\Workflows\Models\WorkflowInstance;

/**
 * Template variables describing what a workflow is about, in one locale. Requests are the main
 * subject; any other instance falls back to its workflow's name.
 */
final readonly class WorkflowSubject
{
    public function request(WorkflowInstance $instance): ?EmployeeRequest
    {
        if ($instance->subject_type !== EmployeeRequest::class || $instance->subject_id === null) {
            return null;
        }

        return EmployeeRequest::query()->with(['type', 'requester'])->find($instance->subject_id);
    }

    /**
     * @return array<string, string>
     */
    public function variables(WorkflowInstance $instance, ?EmployeeRequest $request, User $recipient, string $locale): array
    {
        $requester = $request->requester ?? ($instance->requester_employee_id === null ? null : Employee::query()->withTrashed()->find($instance->requester_employee_id));
        $type = $request?->type?->getTranslation('name', $locale);
        $workflowName = $instance->definition?->getTranslation('name', $locale);

        return [
            'name' => Employee::query()->where('user_id', $recipient->id)->first()?->displayName($locale) ?? $recipient->name,
            'requester' => $requester?->displayName($locale) ?? '',
            'request_type' => is_string($type) && $type !== '' ? $type : (is_string($workflowName) ? $workflowName : ''),
            'request_number' => $request->number ?? '',
            'link' => $this->url($request),
        ];
    }

    public function url(?EmployeeRequest $request): string
    {
        return $request instanceof EmployeeRequest ? route('requests.show', $request) : route('approvals.index');
    }
}
