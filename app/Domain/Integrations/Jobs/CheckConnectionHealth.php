<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Jobs;

use App\Domain\Integrations\Actions\CheckIntegrationHealth;
use App\Domain\Integrations\Models\IntegrationConnection;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;

/** One connection's health check, off the request path (`integrations:health`, every 5 minutes). */
#[Queue('integrations')]
#[Tries(1)]
#[UniqueFor(240)]
final class CheckConnectionHealth implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $connectionId) {}

    public function uniqueId(): string
    {
        return (string) $this->connectionId;
    }

    public function handle(CheckIntegrationHealth $check): void
    {
        $connection = IntegrationConnection::query()->find($this->connectionId);

        if ($connection instanceof IntegrationConnection) {
            $check->handle($connection);
        }
    }
}
