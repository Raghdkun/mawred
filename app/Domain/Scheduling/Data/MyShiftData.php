<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Data;

use App\Domain\Scheduling\Models\RosterShift;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A shift as its person sees it (handoff 23), or an open shift they could claim. */
#[TypeScript]
final class MyShiftData extends Data
{
    /**
     * @param  array<string, string>|null  $name
     * @param  array<string, string>  $branch
     */
    public function __construct(
        public string $id,
        public string $date,
        public string $startsAt,
        public string $endsAt,
        public int $breakMinutes,
        public int $paidMinutes,
        public ?array $name,
        public int $color,
        public array $branch,
        public bool $claimed = false,
    ) {}

    /**
     * Expects `template` and `branch` loaded.
     */
    public static function fromModel(RosterShift $shift, bool $claimed = false): self
    {
        $name = null;

        if ($shift->template !== null) {
            $name = [];

            foreach ($shift->template->getTranslations('name') as $locale => $text) {
                $name[(string) $locale] = is_string($text) ? $text : '';
            }
        }

        $branch = [];

        foreach ($shift->branch?->getTranslations('name') ?? [] as $locale => $text) {
            $branch[(string) $locale] = is_string($text) ? $text : '';
        }

        return new self(
            id: $shift->ulid,
            date: $shift->date->toDateString(),
            startsAt: substr($shift->starts_at, 0, 5),
            endsAt: substr($shift->ends_at, 0, 5),
            breakMinutes: $shift->break_minutes,
            paidMinutes: $shift->paidMinutes(),
            name: $name,
            color: $shift->template->color ?? 1,
            branch: $branch,
            claimed: $claimed,
        );
    }
}
