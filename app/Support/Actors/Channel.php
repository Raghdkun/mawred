<?php

declare(strict_types=1);

namespace App\Support\Actors;

/**
 * Where an action originated, recorded on every audit entry (ADR-0006).
 */
enum Channel: string
{
    case Web = 'web';
    case Api = 'api';
    case EmailLink = 'email_link';
    case WhatsApp = 'whatsapp';
    case Cognito = 'cognito';
    case System = 'system';
    case Import = 'import';
}
