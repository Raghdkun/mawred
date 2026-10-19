<?php

declare(strict_types=1);

namespace App\Domain\Reports\Enums;

/** Groups in the report library (handoff 36). */
enum ReportCategory: string
{
    case People = 'people';
    case Leave = 'leave';
    case Time = 'time';
    case Requests = 'requests';
    case Hiring = 'hiring';
    case Training = 'training';
    case Payroll = 'payroll';
    case Admin = 'admin';
}
