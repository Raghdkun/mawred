<?php

declare(strict_types=1);

namespace App\Domain\Reports\Contracts;

use App\Domain\Access\Models\User;
use App\Domain\Reports\Enums\ReportCategory;
use App\Domain\Reports\Support\ReportColumn;
use App\Domain\Reports\Support\ReportFilter;
use App\Domain\Reports\Support\ReportParams;
use App\Domain\Reports\Support\ReportSummary;

/**
 * A prebuilt report (D112). Rows are always limited by the scope of the module permission the
 * report names (ADR-0005): a report never shows anything its viewer couldn't see in the module.
 */
interface Report
{
    public function key(): string;

    public function category(): ReportCategory;

    /** @return array{en: string, ar: string} */
    public function title(): array;

    /** @return array{en: string, ar: string} */
    public function description(): array;

    /** The module permission whose scope limits the rows, e.g. `people.view`. */
    public function permission(): string;

    /** @return list<ReportFilter> */
    public function filters(): array;

    /** @return list<ReportColumn> */
    public function columns(): array;

    /**
     * The rows the viewer may see, streamed in order.
     *
     * @return iterable<array<string, string|int|float|null>>
     */
    public function rows(User $user, ReportParams $params, string $locale): iterable;

    /**
     * Headline numbers and a chart from the rows the viewer loaded.
     *
     * @param  list<array<string, string|int|float|null>>  $rows
     */
    public function summarise(array $rows, ReportParams $params, string $locale): ReportSummary;
}
