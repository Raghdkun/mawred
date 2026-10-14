<?php

declare(strict_types=1);

namespace App\Domain\People\Data;

use App\Domain\People\Models\AssetAssignment;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class AssetData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $category,
        public string $assetTag,
        public ?string $serialNumber,
        public string $assignedOn,
        public ?string $dueBackOn,
        public ?string $returnedOn,
        public ?string $conditionNotes,
    ) {}

    public static function fromAssignment(AssetAssignment $asset): self
    {
        return new self(
            id: $asset->ulid,
            name: $asset->name,
            category: $asset->category,
            assetTag: $asset->asset_tag,
            serialNumber: $asset->serial_number,
            assignedOn: $asset->assigned_on->toDateString(),
            dueBackOn: $asset->due_back_on?->toDateString(),
            returnedOn: $asset->returned_on?->toDateString(),
            conditionNotes: $asset->condition_notes,
        );
    }
}
