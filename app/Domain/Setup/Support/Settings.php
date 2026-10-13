<?php

declare(strict_types=1);

namespace App\Domain\Setup\Support;

use App\Domain\Setup\Models\Setting;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Read/write access to grouped settings, cached as one map and invalidated on every write.
 *
 * Keys are addressed as `group.key` (e.g. `company.name`, `setup.completed_at`).
 */
final class Settings
{
    private const string CACHE_KEY = 'mawred:settings:v1';

    /** @var array<string, mixed>|null */
    private ?array $memo = null;

    public function __construct(private readonly Cache $cache) {}

    public function get(string $path, mixed $default = null): mixed
    {
        return array_key_exists($path, $this->all()) ? $this->all()[$path] : $default;
    }

    public function has(string $path): bool
    {
        return array_key_exists($path, $this->all());
    }

    public function set(string $path, mixed $value): void
    {
        [$group, $key] = $this->split($path);

        Setting::query()->updateOrCreate(['group' => $group, 'key' => $key], ['value' => $value]);

        $this->flush();
    }

    public function forget(string $path): void
    {
        [$group, $key] = $this->split($path);

        Setting::query()->where('group', $group)->where('key', $key)->delete();

        $this->flush();
    }

    /**
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        $prefix = $group.'.';
        $values = [];

        foreach ($this->all() as $path => $value) {
            if (str_starts_with($path, $prefix)) {
                $values[substr($path, strlen($prefix))] = $value;
            }
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->memo ??= $this->cache->rememberForever(self::CACHE_KEY, fn (): array => Setting::query()
            ->get(['group', 'key', 'value'])
            ->mapWithKeys(fn (Setting $setting): array => [$setting->group.'.'.$setting->key => $setting->value])
            ->all());
    }

    public function flush(): void
    {
        $this->memo = null;
        $this->cache->forget(self::CACHE_KEY);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function split(string $path): array
    {
        $parts = explode('.', $path, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new \InvalidArgumentException("Setting path [{$path}] must be in the form group.key.");
        }

        return [$parts[0], $parts[1]];
    }
}
