<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Data;

use App\Domain\Scheduling\Models\ShiftTemplate;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ShiftTemplateData extends Data
{
    /**
     * @param  array<string, string>  $name
     */
    public function __construct(
        public string $id,
        public string $code,
        public array $name,
        public string $startsAt,
        public string $endsAt,
        public int $breakMinutes,
        public int $color,
        public ?string $branchId,
        public bool $isActive,
    ) {}

    public static function fromModel(ShiftTemplate $template): self
    {
        $name = [];

        foreach ($template->getTranslations('name') as $locale => $text) {
            $name[(string) $locale] = is_string($text) ? $text : '';
        }

        return new self(
            id: $template->ulid,
            code: $template->code,
            name: $name,
            startsAt: substr($template->starts_at, 0, 5),
            endsAt: substr($template->ends_at, 0, 5),
            breakMinutes: $template->break_minutes,
            color: $template->color,
            branchId: $template->branch?->ulid,
            isActive: $template->is_active,
        );
    }
}
