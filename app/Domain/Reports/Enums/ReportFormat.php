<?php

declare(strict_types=1);

namespace App\Domain\Reports\Enums;

enum ReportFormat: string
{
    case Xlsx = 'xlsx';
    case Csv = 'csv';
    case Pdf = 'pdf';
}
