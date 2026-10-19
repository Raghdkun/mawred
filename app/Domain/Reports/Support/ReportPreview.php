<?php

declare(strict_types=1);

namespace App\Domain\Reports\Support;

use App\Domain\Access\Models\User;
use App\Domain\Reports\Contracts\Report;
use App\Domain\Reports\Data\ReportResultData;

/**
 * Runs a report for the screen: reads up to READ_LIMIT rows for the headline numbers and chart,
 * and sends the first TABLE_LIMIT to the table. Anything bigger is for an export.
 */
final class ReportPreview
{
    public const int READ_LIMIT = 2000;

    public const int TABLE_LIMIT = 200;

    public static function build(Report $report, User $user, ReportParams $params, string $locale): ReportResultData
    {
        $rows = [];
        $truncated = false;

        foreach ($report->rows($user, $params, $locale) as $row) {
            if (count($rows) >= self::READ_LIMIT) {
                $truncated = true;

                break;
            }

            $rows[] = $row;
        }

        $summary = $report->summarise($rows, $params, $locale);

        return new ReportResultData(
            columns: array_map(fn (ReportColumn $c): array => ['key' => $c->key, 'label' => $c->labelIn($locale), 'type' => $c->type], $report->columns()),
            rows: array_slice($rows, 0, self::TABLE_LIMIT),
            total: count($rows),
            truncated: $truncated,
            kpis: $summary->kpis,
            chart: $summary->chart,
        );
    }
}
