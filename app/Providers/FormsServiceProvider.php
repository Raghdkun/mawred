<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Files\ClamAvScanner;
use App\Support\Files\FileScanner;
use App\Support\Files\NullFileScanner;
use Illuminate\Support\ServiceProvider;

/**
 * Forms wiring (ADR-0008): upload scanning through clamd when configured (D20).
 */
final class FormsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(function (): FileScanner {
            $host = config('services.clamav.host');

            return is_string($host) && $host !== ''
                ? new ClamAvScanner($host, is_numeric(config('services.clamav.port')) ? (int) config('services.clamav.port') : 3310)
                : new NullFileScanner;
        });
    }
}
