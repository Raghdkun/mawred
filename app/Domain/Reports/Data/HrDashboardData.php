<?php

declare(strict_types=1);

namespace App\Domain\Reports\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The HR admin home (handoff 6, D123): KPIs, documents expiring soon, attendance today by
 * branch, and integration health. Parts the viewer has no permission for are null.
 */
#[TypeScript]
final class HrDashboardData extends Data
{
    /**
     * @param  list<DashboardKpiData>  $kpis
     * @param  array{total: int, rows: list<array{number: string, name: string, branch: string, document: string, expiresOn: string, daysLeft: int}>, href: string|null}|null  $expiring
     * @param  list<array{branch: string, present: int, late: int, away: int, absent: int, notIn: int}>|null  $attendance
     * @param  list<array{name: string, type: string, status: string, checkedAt: string|null}>|null  $integrations
     */
    public function __construct(
        public array $kpis,
        public ?array $expiring,
        public ?array $attendance,
        public ?array $integrations,
    ) {}
}
