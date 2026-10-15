<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Contracts;

use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Integrations\WhatsApp\OutgoingMessage;
use App\Domain\Integrations\WhatsApp\SendResult;

/** A way to deliver WhatsApp messages (ADR-0015 `WhatsAppChannel`). */
interface WhatsAppDriver
{
    public function type(): IntegrationType;

    public function send(IntegrationConnection $connection, OutgoingMessage $message): SendResult;

    /** One-tap approval buttons (Official); otherwise approvals travel as signed links. */
    public function supportsQuickReplies(): bool;
}
