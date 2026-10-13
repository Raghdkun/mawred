<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Organization;

use App\Domain\Organization\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Position $resource
 */
final class PositionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->ulid,
            'code' => $this->resource->code,
            'title' => $this->resource->getTranslations('title'),
            'department_id' => $this->resource->department?->ulid,
            'grade_id' => $this->resource->grade?->ulid,
            'is_active' => $this->resource->is_active,
        ];
    }
}
