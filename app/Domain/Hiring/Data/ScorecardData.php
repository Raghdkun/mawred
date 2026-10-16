<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Data;

use App\Domain\Hiring\Models\Scorecard;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One panelist's ratings, recommendation and comment (D90). */
#[TypeScript]
final class ScorecardData extends Data
{
    /**
     * @param  array<string, array{rating: int, note: string|null}>  $ratings  criterion ULID => rating
     */
    public function __construct(
        public string $by,
        public array $ratings,
        public ?string $recommendation,
        public ?string $comment,
        public ?string $submittedAt,
    ) {}

    public static function fromScorecard(Scorecard $card): self
    {
        return new self(
            $card->user->name ?? '—',
            $card->ratings,
            $card->recommendation?->value,
            $card->comment,
            $card->submitted_at?->toIso8601String(),
        );
    }
}
