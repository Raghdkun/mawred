<?php

declare(strict_types=1);

namespace App\Domain\Access\Support;

use Closure;

/**
 * Every field key that field permissions can guard (ADR-0007): the built-in sensitive fields
 * plus whatever other domains register at runtime, e.g. People's sensitive custom fields
 * (`employee.custom.<key>`), each with its own label.
 */
final class SensitiveFieldRegistry
{
    /** @var list<Closure(): array<string, array<string, string>>> */
    private array $providers = [];

    /**
     * @param  Closure(): array<string, array<string, string>>  $provider  field key => label per locale
     */
    public function extend(Closure $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_values(array_unique([...PermissionCatalog::SENSITIVE_FIELDS, ...array_keys($this->labels())]));
    }

    public function exists(string $key): bool
    {
        return in_array($key, $this->keys(), true);
    }

    /**
     * Labels for registered (non built-in) keys; built-in keys are labelled by the UI.
     *
     * @return array<string, array<string, string>>
     */
    public function labels(): array
    {
        $labels = [];

        foreach ($this->providers as $provider) {
            $labels = [...$labels, ...$provider()];
        }

        return $labels;
    }
}
