<?php

declare(strict_types=1);

namespace App\Domain\Reports\Support;

use App\Domain\Forms\Support\FieldSpec;
use Carbon\CarbonImmutable;

/**
 * Turns a report's calendar period into UTC instants for timestamp columns, reading the days in
 * the company's timezone.
 */
final class Instants
{
    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable} start inclusive, end exclusive
     */
    public static function between(string $from, string $to): array
    {
        $zone = FieldSpec::string(config('app.timezone'), 'UTC');

        return [
            CarbonImmutable::parse($from, $zone)->startOfDay()->utc(),
            CarbonImmutable::parse($to, $zone)->addDay()->startOfDay()->utc(),
        ];
    }
}
