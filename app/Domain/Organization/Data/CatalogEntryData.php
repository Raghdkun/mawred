<?php

declare(strict_types=1);

namespace App\Domain\Organization\Data;

use App\Domain\Organization\Models\CostCenter;
use App\Domain\Organization\Models\EmploymentType;
use App\Domain\Organization\Models\Grade;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class CatalogEntryData extends Data
{
    /**
     * @param  array<string, string>  $name
     */
    public function __construct(
        public string $id,
        public string $code,
        public array $name,
        public ?int $rank,
        public bool $isActive,
    ) {}

    public static function fromEntry(EmploymentType|Grade|CostCenter $entry): self
    {
        /** @var array<string, string> $name */
        $name = $entry->getTranslations('name');

        return new self(
            id: $entry->ulid,
            code: $entry->code,
            name: $name,
            rank: $entry instanceof Grade ? $entry->rank : null,
            isActive: $entry->is_active,
        );
    }
}
