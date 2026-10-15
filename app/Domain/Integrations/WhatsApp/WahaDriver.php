<?php

declare(strict_types=1);

namespace App\Domain\Integrations\WhatsApp;

use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Integrations\Contracts\WhatsAppDriver;
use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Models\IntegrationConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * "Unofficial — WhatsApp Web (WAHA)" (D8, D31): a sidecar on the private network holding a
 * WhatsApp Web session. Plain text only; approvals always travel as signed links.
 */
final class WahaDriver implements WhatsAppDriver
{
    public function type(): IntegrationType
    {
        return IntegrationType::WhatsAppWaha;
    }

    public function supportsQuickReplies(): bool
    {
        return false;
    }

    public function send(IntegrationConnection $connection, OutgoingMessage $message): SendResult
    {
        try {
            $response = $this->client($connection)->post('api/sendText', [
                'session' => $this->session($connection),
                'chatId' => $message->digits().'@c.us',
                'text' => $message->text,
                'linkPreview' => false,
            ]);
        } catch (Throwable $e) {
            return SendResult::failed(mb_substr($e->getMessage(), 0, 500), 'network');
        }

        if (! $response->successful()) {
            $error = $response->json('message');

            return SendResult::failed(mb_substr(is_string($error) ? $error : $response->body(), 0, 500), (string) $response->status());
        }

        $id = $response->json('id');
        $id = is_array($id) ? ($id['_serialized'] ?? null) : $id;

        return SendResult::sent(is_string($id) ? $id : null);
    }

    /**
     * The session's status: STOPPED, STARTING, SCAN_QR_CODE, WORKING or FAILED.
     */
    public function status(IntegrationConnection $connection): ?string
    {
        $response = $this->client($connection)->get('api/sessions/'.rawurlencode($this->session($connection)));

        if ($response->status() === 404) {
            return 'STOPPED';
        }

        $response->throw();
        $status = $response->json('status');

        return is_string($status) ? $status : null;
    }

    /**
     * Creates (or updates) and starts the session with Mawred's webhook, signed with HMAC.
     */
    public function start(IntegrationConnection $connection, string $webhookUrl): void
    {
        $session = $this->session($connection);
        $config = ['webhooks' => [[
            'url' => $webhookUrl,
            'events' => ['session.status', 'message'],
            'hmac' => ['key' => FieldSpec::string($connection->credentials['webhook_key'] ?? null)],
        ]]];
        $existing = $this->client($connection)->get('api/sessions/'.rawurlencode($session));

        if ($existing->status() === 404) {
            $this->client($connection)->post('api/sessions', ['name' => $session, 'config' => $config])->throw();

            return;
        }

        $this->client($connection)->put('api/sessions/'.rawurlencode($session), ['name' => $session, 'config' => $config])->throw();
        $this->client($connection)->post('api/sessions/'.rawurlencode($session).'/start')->throw();
    }

    /** The current pairing QR as a PNG data URI (WAHA renders it; no QR library needed here). */
    public function qr(IntegrationConnection $connection): ?string
    {
        $response = $this->client($connection)->get('api/'.rawurlencode($this->session($connection)).'/auth/qr', ['format' => 'image']);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json('data');
        $mime = $response->json('mimetype');

        return is_string($data) && preg_match('/^[A-Za-z0-9+\/=]+$/', $data) === 1 ? 'data:'.(is_string($mime) && $mime === 'image/png' ? $mime : 'image/png').';base64,'.$data : null;
    }

    public function logout(IntegrationConnection $connection): void
    {
        $this->client($connection)->post('api/sessions/'.rawurlencode($this->session($connection)).'/logout');
    }

    public function client(IntegrationConnection $connection): PendingRequest
    {
        $timeout = config('services.whatsapp.timeout');

        return Http::baseUrl(rtrim(FieldSpec::string($connection->config['base_url'] ?? null), '/').'/')
            ->withHeaders(['X-Api-Key' => FieldSpec::string($connection->credentials['api_key'] ?? null)])
            ->acceptJson()
            ->timeout(is_int($timeout) ? $timeout : 15);
    }

    public function session(IntegrationConnection $connection): string
    {
        return FieldSpec::string($connection->config['session'] ?? null, 'default');
    }
}
