<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Requests;

use App\Domain\Requests\Models\EmployeeRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A request for the mobile app; answers and the timeline only on show.
 *
 * @property-read EmployeeRequest $resource
 */
final class RequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $model = $this->resource;
        $type = $model->requestType();

        return [
            'id' => $model->ulid,
            'number' => $model->number,
            'type' => ['id' => $type->ulid, 'code' => $type->code, 'name' => $type->getTranslations('name')],
            'status' => $model->status->value,
            'requester' => $model->requester?->displayName(app()->getLocale()),
            'submitted_at' => $model->submitted_at->toIso8601String(),
            'decided_at' => $model->decided_at?->toIso8601String(),
            'answers' => $this->when($request->routeIs('api.v1.requests.show'), fn (): array => $model->submission()->firstOrFail()->data),
        ];
    }
}
