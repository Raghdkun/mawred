<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Requests;

use App\Domain\Requests\Data\TaskData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An open approval or to-do for the mobile app's inbox.
 *
 * @property-read TaskData $resource
 */
final class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $task = $this->resource;

        return [
            'id' => $task->id,
            'kind' => $task->kind,
            'step' => $task->step,
            'assigned_at' => $task->assignedAt,
            'due_at' => $task->dueAt,
            'overdue' => $task->overdue,
            'decisions' => $task->decisions,
            'about' => $task->about,
            'request' => $task->request === null ? null : [
                'id' => $task->request->id,
                'number' => $task->request->number,
                'type' => $task->request->typeName,
                'requester' => $task->request->requester,
                'status' => $task->request->status,
            ],
        ];
    }
}
