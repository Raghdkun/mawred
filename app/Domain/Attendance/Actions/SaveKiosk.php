<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Actions;

use App\Domain\Attendance\Models\Kiosk;
use App\Domain\Organization\Models\Branch;
use Illuminate\Support\Str;

/**
 * Creates a kiosk (or issues it a new link): returns the one-time link token, shown once. The
 * old link stops working at once.
 */
final readonly class SaveKiosk
{
    /**
     * @return array{kiosk: Kiosk, token: string}
     */
    public function handle(Branch $branch, string $name, ?Kiosk $kiosk = null): array
    {
        $token = Str::random(48);
        $kiosk ??= new Kiosk(['secret' => Str::random(64)]);
        $kiosk->fill(['branch_id' => $branch->id, 'name' => $name, 'token_hash' => hash('sha256', $token), 'is_active' => true])->save();

        return ['kiosk' => $kiosk, 'token' => $token];
    }
}
