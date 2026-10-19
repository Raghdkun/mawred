<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Attendance\Models\Kiosk;
use App\Domain\Attendance\Support\KioskCodes;
use App\Http\Controllers\Controller;
use App\Support\Qr\QrSvg;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The kiosk screen (D70): opened on a branch device with its secret link; it polls for the
 * current QR code, an HMAC of a 20-second slot that people scan from the app to check in.
 */
final class KioskController extends Controller
{
    public function show(string $token): Response
    {
        $kiosk = $this->kiosk($token);

        return Inertia::render('public/kiosk', ['name' => $kiosk->name, 'branch' => $kiosk->branch?->getTranslations('name') ?? [], 'token' => $token]);
    }

    public function code(string $token): JsonResponse
    {
        $kiosk = $this->kiosk($token);
        $kiosk->forceFill(['last_seen_at' => now()])->saveQuietly();
        $now = now()->getTimestamp();
        $code = KioskCodes::code($kiosk->ulid, $kiosk->secret, $now);
        $svg = QrSvg::render($code);

        return response()->json([
            'code' => $code,
            'svg' => $svg,
            'refreshIn' => KioskCodes::SLOT_SECONDS - ($now % KioskCodes::SLOT_SECONDS),
        ])->header('Cache-Control', 'no-store');
    }

    private function kiosk(string $token): Kiosk
    {
        $kiosk = Kiosk::query()->with('branch')->where('token_hash', hash('sha256', $token))->where('is_active', true)->first();
        abort_unless($kiosk instanceof Kiosk, 404);

        return $kiosk;
    }
}
