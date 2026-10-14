<?php

declare(strict_types=1);

namespace App\Domain\Organization\Data;

use App\Domain\Organization\Models\Position;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class PositionData extends Data
{
    /**
     * @param  array<string, string>  $title
     * @param  array<string, string>|null  $departmentName
     */
    public function __construct(
        public string $id,
        public string $code,
        public array $title,
        public ?string $departmentId,
        public ?array $departmentName,
        public ?string $gradeId,
        public bool $isActive,
    ) {}

    public static function fromPosition(Position $position): self
    {
        /** @var array<string, string> $title */
        $title = $position->getTranslations('title');
        /** @var array<string, string>|null $departmentName */
        $departmentName = $position->department?->getTranslations('name');

        return new self(
            id: $position->ulid,
            code: $position->code,
            title: $title,
            departmentId: $position->department?->ulid,
            departmentName: $departmentName,
            gradeId: $position->grade?->ulid,
            isActive: $position->is_active,
        );
    }
}
