<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Engine;

/** Registry of Update Record actions, filled by each domain's service provider. */
final class RecordUpdates
{
    /** @var array<string, RecordUpdate> */
    private array $updates = [];

    public function register(RecordUpdate $update): void
    {
        $this->updates[$update->key()] = $update;
    }

    public function find(string $key): ?RecordUpdate
    {
        return $this->updates[$key] ?? null;
    }

    /**
     * @return array<string, RecordUpdate>
     */
    public function all(): array
    {
        return $this->updates;
    }
}
