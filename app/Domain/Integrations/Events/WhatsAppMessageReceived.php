<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Events;

use App\Domain\Integrations\Enums\IntegrationType;

/**
 * A text message arrived from a WhatsApp number (either driver). Other domains decide whether it
 * is theirs, e.g. a candidate's reply in Hiring (D94).
 */
final readonly class WhatsAppMessageReceived
{
    public function __construct(public IntegrationType $driver, public string $from, public string $text) {}
}
