<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Enums;

enum MessageChannel: string
{
    case Email = 'email';
    case WhatsApp = 'whatsapp';
}
