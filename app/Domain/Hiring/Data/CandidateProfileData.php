<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Data;

use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\HiringTag;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** The candidate header on their profile (handoff 30). */
#[TypeScript]
final class CandidateProfileData extends Data
{
    /**
     * @param  list<string>  $tags
     * @param  array{id: string, name: string}|null  $duplicateOf
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $email,
        public ?string $phone,
        public string $locale,
        public ?string $location,
        public string $source,
        public bool $talentPool,
        public ?string $talentPoolConsentedAt,
        public ?string $consentedAt,
        public array $tags,
        public ?array $duplicateOf,
        public string $createdAt,
    ) {}

    public static function fromCandidate(Candidate $candidate): self
    {
        $duplicate = $candidate->possibleDuplicateOf;

        return new self(
            $candidate->ulid,
            $candidate->name,
            $candidate->email,
            $candidate->phone,
            $candidate->locale,
            $candidate->location,
            $candidate->source->value,
            $candidate->talent_pool,
            $candidate->talent_pool_consented_at?->toIso8601String(),
            $candidate->consented_at?->toIso8601String(),
            array_values($candidate->tags->map(fn (HiringTag $t): string => $t->name)->all()),
            $duplicate instanceof Candidate ? ['id' => $duplicate->ulid, 'name' => $duplicate->name] : null,
            $candidate->created_at->toIso8601String(),
        );
    }
}
