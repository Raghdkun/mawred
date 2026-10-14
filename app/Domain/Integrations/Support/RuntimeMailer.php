<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Support;

use App\Domain\Integrations\Models\IntegrationConnection;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\QueryException;
use Illuminate\Mail\MailManager;

/**
 * Builds the `mawred` mailer from the email connection in the vault (ADR-0015) and makes it the
 * default. Applied when the mail manager is first resolved; long-running workers re-apply when
 * the connection changes (a version number in the cache, never the secret itself).
 */
final class RuntimeMailer
{
    public const string VERSION_KEY = 'mawred:mailer:version';

    private ?int $applied = null;

    public function __construct(private readonly Cache $cache) {}

    public static function connection(): ?IntegrationConnection
    {
        return IntegrationConnection::query()->where('type', 'email')->where('name', 'default')->first();
    }

    public function apply(MailManager $manager): void
    {
        if (config('mail.runtime_config', true) !== true) {
            return;
        }

        $this->applied = $this->version();

        try {
            $connection = self::connection();
        } catch (QueryException) {
            return; // Not migrated yet (fresh install).
        }

        if (! $connection instanceof IntegrationConnection || $connection->status === 'not_configured') {
            return;
        }

        config([
            'mail.mailers.mawred' => self::transport($connection),
            'mail.default' => 'mawred',
            'mail.from.address' => $connection->config['from_address'] ?? config('mail.from.address'),
            'mail.from.name' => $connection->config['from_name'] ?? config('mail.from.name'),
        ]);
        $manager->purge('mawred');
        $manager->purge();
    }

    public function refreshIfStale(MailManager $manager): void
    {
        if ($this->applied !== null && $this->applied !== $this->version()) {
            $this->apply($manager);
        }
    }

    public function bump(): void
    {
        $this->cache->forever(self::VERSION_KEY, $this->version() + 1);
    }

    /**
     * @return array<string, mixed>
     */
    public static function transport(IntegrationConnection $connection): array
    {
        $config = $connection->config;

        return [
            'transport' => 'smtp',
            'scheme' => ($config['security'] ?? 'starttls') === 'ssl' ? 'smtps' : 'smtp',
            'host' => $config['host'] ?? '',
            'port' => is_numeric($config['port'] ?? null) ? (int) $config['port'] : 587,
            'username' => $config['username'] ?? null,
            'password' => $connection->credentials['password'] ?? null,
            'timeout' => 15,
        ];
    }

    private function version(): int
    {
        $version = $this->cache->get(self::VERSION_KEY, 0);

        return is_int($version) ? $version : 0;
    }
}
