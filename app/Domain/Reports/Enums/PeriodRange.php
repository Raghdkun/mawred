<?php

declare(strict_types=1);

namespace App\Domain\Reports\Enums;

use Carbon\CarbonImmutable;

/**
 * A period relative to the day a report runs: quick picks in the viewer, and what a schedule
 * stores instead of fixed dates so each run covers its own period.
 */
enum PeriodRange: string
{
    case ThisMonth = 'this_month';
    case LastMonth = 'last_month';
    case Last7Days = 'last_7_days';
    case Last30Days = 'last_30_days';
    case ThisYear = 'this_year';

    /**
     * @return array{0: string, 1: string} from and to, inclusive
     */
    public function between(CarbonImmutable $today): array
    {
        return match ($this) {
            self::ThisMonth => [$today->startOfMonth()->toDateString(), $today->toDateString()],
            self::LastMonth => [$today->subMonthNoOverflow()->startOfMonth()->toDateString(), $today->subMonthNoOverflow()->endOfMonth()->toDateString()],
            self::Last7Days => [$today->subDays(6)->toDateString(), $today->toDateString()],
            self::Last30Days => [$today->subDays(29)->toDateString(), $today->toDateString()],
            self::ThisYear => [$today->startOfYear()->toDateString(), $today->toDateString()],
        };
    }
}
