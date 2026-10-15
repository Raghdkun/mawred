<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Events;

use App\Domain\Integrations\Enums\IntegrationStatus;
use App\Domain\Integrations\Models\IntegrationConnection;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** A connection's health badge changed (integration admins are alerted). */
final readonly class IntegrationHealthChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public IntegrationConnection $connection, public IntegrationStatus $from, public IntegrationStatus $to) {}
}
