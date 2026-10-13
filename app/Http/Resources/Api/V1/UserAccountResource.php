<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Access\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read User $resource
 */
final class UserAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->ulid,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'status' => $this->resource->status,
        ];
    }
}
