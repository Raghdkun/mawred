<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Integrations\Models\WhatsAppTemplate;
use App\Domain\Integrations\Support\Connections;
use App\Domain\Integrations\WhatsApp\OfficialDriver;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Database\ConnectionInterface;
use Throwable;

/** Copies the WhatsApp Business account's templates and their approval status from Meta. */
final readonly class SyncWhatsAppTemplates
{
    public function __construct(private OfficialDriver $driver, private ConnectionInterface $db) {}

    public function handle(): int
    {
        $connection = Connections::find(IntegrationType::WhatsAppOfficial);

        if (! $connection instanceof IntegrationConnection) {
            throw new DomainRuleViolation('whatsapp.not_configured', __('Set up WhatsApp (Official) first.'));
        }

        try {
            $templates = $this->driver->templates($connection);
        } catch (Throwable $e) {
            throw new DomainRuleViolation('whatsapp.sync_failed', __('Meta didn’t return the templates: :reason', ['reason' => mb_substr($e->getMessage(), 0, 200)]));
        }

        return $this->db->transaction(function () use ($templates): int {
            $seen = [];

            foreach ($templates as $template) {
                if (! is_string($template['name'] ?? null) || ! is_string($template['language'] ?? null)) {
                    continue;
                }

                WhatsAppTemplate::query()->updateOrCreate(['name' => $template['name'], 'language' => $template['language']], [
                    'category' => is_string($template['category'] ?? null) ? $template['category'] : null,
                    'status' => is_string($template['status'] ?? null) ? $template['status'] : 'UNKNOWN',
                    'components' => is_array($template['components'] ?? null) ? array_values($template['components']) : [],
                    'synced_at' => now(),
                ]);
                $seen[] = $template['name'].'|'.$template['language'];
            }

            WhatsAppTemplate::query()->get()->reject(fn (WhatsAppTemplate $t): bool => in_array($t->name.'|'.$t->language, $seen, true))->each->delete();

            return count($seen);
        });
    }
}
