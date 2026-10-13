<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Organization;

use App\Domain\Organization\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Department $resource
 */
final class DepartmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->ulid,
            'code' => $this->resource->code,
            'name' => $this->resource->getTranslations('name'),
            'parent_id' => $this->resource->parent?->ulid,
            'depth' => $this->resource->depth,
            'is_active' => $this->resource->is_active,
        ];
    }
}
