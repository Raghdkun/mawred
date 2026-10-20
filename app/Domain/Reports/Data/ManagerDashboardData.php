<?php

declare(strict_types=1);

namespace App\Domain\Reports\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A manager's team today (handoff 5, D123): who's in, late, away or not in yet, who's away,
 * and today's late arrivals, on the manager's branch date.
 */
#[TypeScript]
final class ManagerDashboardData extends Data
{
    /**
     * @param  list<DashboardKpiData>  $kpis
     * @param  array{in: int, late: int, away: int, notIn: int}  $today
     * @param  list<array{id: string, name: string, until: string|null}>  $away
     * @param  list<array{id: string, name: string, minutes: int, firstIn: string|null, timezone: string}>  $late
     */
    public function __construct(
        public string $date,
        public array $kpis,
        public array $today,
        public array $away,
        public array $late,
        public ?string $attendanceHref,
    ) {}
}
