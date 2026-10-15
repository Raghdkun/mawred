<?php

declare(strict_types=1);

namespace App\Domain\Integrations\WhatsApp;

final readonly class SendResult
{
    public function __construct(public bool $sent, public ?string $providerId = null, public ?string $errorCode = null, public ?string $error = null, public ?string $template = null) {}

    public static function sent(?string $providerId, ?string $template = null): self
    {
        return new self(true, $providerId, template: $template);
    }

    public static function failed(string $error, ?string $code = null): self
    {
        return new self(false, null, $code, $error);
    }
}
