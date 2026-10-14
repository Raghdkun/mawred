<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Enums;

/** Where a decision was made (link approvals record email or whatsapp). */
enum DecisionChannel: string
{
    case Web = 'web';
    case Api = 'api';
    case Email = 'email';
    case WhatsApp = 'whatsapp';
    case System = 'system';
}
