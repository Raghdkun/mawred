<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Actions;

use App\Domain\Attendance\Models\Kiosk;

/** Switches a kiosk off: its link and codes stop working. */
final readonly class RetireKiosk
{
    public function handle(Kiosk $kiosk): void
    {
        $kiosk->forceFill(['is_active' => false])->save();
    }
}
