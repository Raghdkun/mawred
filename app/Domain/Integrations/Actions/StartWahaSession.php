<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Integrations\Support\Connections;
use App\Domain\Integrations\WhatsApp\WahaDriver;
use App\Support\Errors\DomainRuleViolation;
use Throwable;

/** Starts (or restarts) the WhatsApp Web session so a phone can pair by scanning the QR. */
final readonly class StartWahaSession
{
    public function __construct(private WahaDriver $driver) {}

    public function handle(): void
    {
        $connection = Connections::find(IntegrationType::WhatsAppWaha);

        if (! $connection instanceof IntegrationConnection) {
            throw new DomainRuleViolation('whatsapp.not_configured', __('Save the sidecar settings first.'));
        }

        try {
            $this->driver->start($connection, route('hooks.whatsapp.waha'));
        } catch (Throwable $e) {
            throw new DomainRuleViolation('whatsapp.waha_unreachable', __('The sidecar didn’t respond: :reason', ['reason' => mb_substr($e->getMessage(), 0, 200)]));
        }
    }
}
