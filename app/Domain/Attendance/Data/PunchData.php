<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Data;

use App\Domain\Attendance\Models\AttendanceEvent;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** A punch in someone's list (handoff 25) or a day's detail (handoff 26). */
#[TypeScript]
final class PunchData extends Data
{
    public function __construct(
        public string $id,
        public string $kind,
        public string $source,
        public string $occurredAt,
        public string $date,
        public string $geofence,
        public bool $flagged,
        public ?string $reason,
        public bool $voided,
    ) {}

    public static function fromModel(AttendanceEvent $event): self
    {
        return new self(
            id: $event->ulid,
            kind: $event->kind->value,
            source: $event->source->value,
            occurredAt: $event->occurred_at->toIso8601String(),
            date: $event->local_date->toDateString(),
            geofence: $event->geofence->value,
            flagged: $event->flagged,
            reason: $event->reason,
            voided: $event->voided_at !== null,
        );
    }
}
