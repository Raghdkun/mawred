<?php

declare(strict_types=1);

namespace App\Support\Health;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * What `/health/ready` checks (ADR-0019). The database, Redis (cache, queues, sessions) and the
 * private disk must answer, or the instance takes no traffic and a deploy rolls back. Gotenberg
 * only renders PDFs, so it is reported but never takes the app out of service.
 */
final class ReadinessProbe
{
    public const array REQUIRED = ['database', 'redis', 'storage'];

    /**
     * @return array<string, bool>
     */
    public function run(): array
    {
        return [
            'database' => $this->passes(fn (): bool => DB::connection()->select('select 1 as ok') !== []),
            'redis' => $this->passes(fn (): bool => (bool) Redis::connection()->ping()),
            'storage' => $this->passes(function (): bool {
                $path = 'tmp/health-'.Str::lower((string) Str::ulid());
                $disk = Storage::disk('local');

                return $disk->put($path, 'ok') && $disk->get($path) === 'ok' && $disk->delete($path);
            }),
            'gotenberg' => $this->passes(fn (): bool => Http::timeout(2)->get(rtrim(config()->string('services.gotenberg.url'), '/').'/health')->successful()),
        ];
    }

    /**
     * @param  array<string, bool>  $checks
     */
    public static function isReady(array $checks): bool
    {
        return array_all(self::REQUIRED, fn (string $name): bool => $checks[$name] ?? false);
    }

    /**
     * @param  callable(): bool  $check
     */
    private function passes(callable $check): bool
    {
        try {
            return $check();
        } catch (Throwable) {
            return false;
        }
    }
}
