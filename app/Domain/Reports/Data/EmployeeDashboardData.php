<?php

declare(strict_types=1);

namespace App\Domain\Reports\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The employee home's check-in card and agenda (handoff 4, D123).
 */
#[TypeScript]
final class EmployeeDashboardData extends Data
{
    /**
     * @param  array{date: string, timezone: string, status: string|null, firstIn: string|null, lastOut: string|null, shift: array{startsAt: string, endsAt: string}|null, next: 'in'|'out'}|null  $clock
     * @param  list<array{key: string, kind: 'shift'|'meeting'|'booking'|'interview'|'training'|'leave'|'holiday', title: string|null, date: string, startsAt: string|null, endsAt: string|null, timezone: string|null}>  $agenda
     */
    public function __construct(
        public ?array $clock,
        public array $agenda,
    ) {}
}
