<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Organization;

use App\Domain\Organization\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Branch $resource
 */
final class BranchResource extends JsonResource
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
            'timezone' => $this->resource->timezone,
            'country_code' => $this->resource->country_code,
            'default_currency' => $this->resource->default_currency,
            'weekend_days' => $this->resource->weekend_days,
            'is_active' => $this->resource->is_active,
        ];
    }
}
