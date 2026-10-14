<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Policies;

use App\Domain\Policies\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeaveType
 */
final class LeaveTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'code' => $this->code,
            'name' => $this->getTranslations('name'),
            'unit' => $this->unit,
            'is_paid' => $this->is_paid,
        ];
    }
}
