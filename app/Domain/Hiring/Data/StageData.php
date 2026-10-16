<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Data;

use App\Domain\Hiring\Models\PipelineStage;
use App\Domain\Hiring\Support\Texts;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A pipeline stage (board column). */
#[TypeScript]
final class StageData extends Data
{
    /**
     * @param  array{en: string, ar: string}  $names
     * @param  array<string, mixed>|null  $automation  stage automations (D93)
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $names,
        public string $kind,
        public int $position,
        public ?array $automation = null,
    ) {}

    public static function fromStage(PipelineStage $stage): self
    {
        $names = Texts::pair($stage, 'name');

        return new self(
            $stage->ulid,
            Texts::one($stage, 'name') ?? $names['en'],
            ['en' => $names['en'], 'ar' => $names['ar'] !== '' ? $names['ar'] : $names['en']],
            $stage->kind->value,
            $stage->position,
            $stage->automation,
        );
    }
}
