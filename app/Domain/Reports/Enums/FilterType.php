<?php

declare(strict_types=1);

namespace App\Domain\Reports\Enums;

/** The filters a report can ask for (the viewer's filter bar, handoff 36). */
enum FilterType: string
{
    /** From and to dates (Y-m-d). */
    case Period = 'period';
    /** A calendar month (Y-m). */
    case Month = 'month';
    case Year = 'year';
    /** A day the report is "as of" (Y-m-d). */
    case AsOf = 'as_of';
    /** Look ahead this many days (expiring documents and certificates). */
    case Days = 'days';
    case Branch = 'branch';
    case Department = 'department';
    /** One of a fixed list the report provides (course, leave type…). */
    case Choice = 'choice';
}
