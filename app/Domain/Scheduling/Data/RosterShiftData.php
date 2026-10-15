<?php

declare(strict_types=1);

namespace App\Domain\Scheduling\Data;

use App\Domain\Scheduling\Models\RosterShift;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A shift in the planner's flat payload (architecture: shifts keyed by employee and date). */
#[TypeScript]
final class RosterShiftData extends Data
{
    /**
     * @param  list<string>  $conflicts
     */
    public function __construct(
        public string $id,
        public ?string $employeeId,
        public string $date,
        public string $startsAt,
        public string $endsAt,
        public int $breakMinutes,
        public int $paidMinutes,
        public ?string $templateId,
        public int $color,
        public string $status,
        public ?string $positionId,
        public ?string $note,
        public bool $autoAssign,
        public int $claims = 0,
        public array $conflicts = [],
    ) {}

    /**
     * Expects `employee`, `template` and `position` loaded (and `claims_count` for open shifts).
     *
     * @param  list<string>  $conflicts
     */
    public static function fromModel(RosterShift $shift, array $conflicts = []): self
    {
        $claims = $shift->getAttribute('claims_count');

        return new self(
            id: $shift->ulid,
            employeeId: $shift->employee?->ulid,
            date: $shift->date->toDateString(),
            startsAt: substr($shift->starts_at, 0, 5),
            endsAt: substr($shift->ends_at, 0, 5),
            breakMinutes: $shift->break_minutes,
            paidMinutes: $shift->paidMinutes(),
            templateId: $shift->template?->ulid,
            color: $shift->template->color ?? 1,
            status: $shift->status->value,
            positionId: $shift->position?->ulid,
            note: $shift->note,
            autoAssign: $shift->auto_assign,
            claims: is_int($claims) ? $claims : 0,
            conflicts: $conflicts,
        );
    }
}
