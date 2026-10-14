<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Requests;

use App\Domain\Forms\Models\FormVersion;
use App\Domain\Requests\Models\RequestType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A request type for the mobile app. `form` is the published MFS v1 schema, included on show.
 *
 * @property-read RequestType $resource
 */
final class RequestTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->resource;
        $versionId = $type->form?->published_version_id;

        return [
            'id' => $type->ulid,
            'code' => $type->code,
            'name' => $type->getTranslations('name'),
            'description' => $type->getTranslations('description'),
            'category' => $type->category->value,
            'icon' => $type->icon,
            'sensitive' => $type->is_sensitive,
            'form' => $this->when($request->routeIs('api.v1.request-types.show') && $versionId !== null, fn (): ?array => FormVersion::query()->find($versionId)?->schema),
        ];
    }
}
