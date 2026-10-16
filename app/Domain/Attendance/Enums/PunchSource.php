<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Enums;

/** Where a punch came from: the employee's phone, a kiosk QR, an HR correction or an import. */
enum PunchSource: string
{
    case Pwa = 'pwa';
    case Kiosk = 'kiosk';
    case Correction = 'correction';
    case Import = 'import';
}
