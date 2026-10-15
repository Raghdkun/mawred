<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Integrations\Enums\IntegrationStatus;
use App\Domain\Integrations\Jobs\CheckConnectionHealth;
use App\Domain\Integrations\Models\IntegrationConnection;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('integrations:health')]
#[Description('Check every configured integration and alert on changes (ADR-0015)')]
final class IntegrationsHealth extends Command
{
    public function handle(): int
    {
        foreach (IntegrationConnection::query()->where('status', '!=', IntegrationStatus::NotConfigured->value)->get(['id']) as $connection) {
            dispatch(new CheckConnectionHealth($connection->id));
        }

        return self::SUCCESS;
    }
}
