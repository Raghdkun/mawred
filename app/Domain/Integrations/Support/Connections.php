<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Support;

use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Models\IntegrationConnection;

/** Looks up the single `default` connection of a type (ADR-0015). */
final class Connections
{
    public static function find(IntegrationType $type): ?IntegrationConnection
    {
        return IntegrationConnection::query()->where('type', $type->value)->where('name', 'default')->first();
    }

    public static function findOrNew(IntegrationType $type): IntegrationConnection
    {
        return self::find($type) ?? new IntegrationConnection(['type' => $type->value, 'name' => 'default']);
    }
}
