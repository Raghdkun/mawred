<?php

declare(strict_types=1);

namespace App\Domain\Integrations\WhatsApp;

use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Integrations\Contracts\WhatsAppDriver;
use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Models\IntegrationConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Meta's WhatsApp Business Cloud API (Graph version pinned in `services.whatsapp`). Outside the
 * 24-hour customer-service window only approved templates go out; quick-reply payloads carry
 * the approval token. Inside it, plain text or reply buttons.
 */
final class OfficialDriver implements WhatsAppDriver
{
    public function type(): IntegrationType
    {
        return IntegrationType::WhatsAppOfficial;
    }

    public function supportsQuickReplies(): bool
    {
        return true;
    }

    public function send(IntegrationConnection $connection, OutgoingMessage $message): SendResult
    {
        $body = ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $message->digits()];

        if ($message->inServiceWindow && $message->buttons !== []) {
            $body += ['type' => 'interactive', 'interactive' => [
                'type' => 'button',
                'body' => ['text' => mb_substr($message->text, 0, 1024)],
                'action' => ['buttons' => array_map(fn (array $button): array => ['type' => 'reply', 'reply' => ['id' => mb_substr($button['id'], 0, 256), 'title' => mb_substr($button['title'], 0, 20)]], array_slice($message->buttons, 0, 3))],
            ]];
        } elseif ($message->inServiceWindow || $message->template === null) {
            if (! $message->inServiceWindow) {
                return SendResult::failed(__('No approved template for this message, and the 24-hour window is closed.'), 'no_template');
            }

            $body += ['type' => 'text', 'text' => ['body' => mb_substr($message->text, 0, 4096), 'preview_url' => false]];
        } else {
            $components = [];

            if ($message->template['params'] !== []) {
                $components[] = ['type' => 'body', 'parameters' => array_map(fn (string $value): array => ['type' => 'text', 'text' => mb_substr($value, 0, 1000)], $message->template['params'])];
            }

            foreach ($message->template['buttons'] as $index => $payload) {
                $components[] = ['type' => 'button', 'sub_type' => 'quick_reply', 'index' => (string) $index, 'parameters' => [['type' => 'payload', 'payload' => $payload]]];
            }

            $body += ['type' => 'template', 'template' => ['name' => $message->template['name'], 'language' => ['code' => $message->template['language']], 'components' => $components]];
        }

        try {
            $response = $this->client($connection)->post($this->phonePath($connection).'/messages', $body);
        } catch (Throwable $e) {
            return SendResult::failed(mb_substr($e->getMessage(), 0, 500), 'network');
        }

        if (! $response->successful()) {
            return SendResult::failed(self::error($response), self::errorCode($response));
        }

        $id = $response->json('messages.0.id');

        return SendResult::sent(is_string($id) ? $id : null, $body['type'] === 'template' ? $message->template['name'] ?? null : null);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function templates(IntegrationConnection $connection): array
    {
        $waba = FieldSpec::string($connection->config['waba_id'] ?? null);
        $templates = [];
        $url = "{$waba}/message_templates?fields=name,language,status,category,components&limit=100";

        for ($page = 0; $page < 20 && $url !== ''; $page++) {
            $response = $this->client($connection)->get($url);
            $response->throw();

            $data = $response->json('data');

            foreach (is_array($data) ? $data : [] as $template) {
                if (is_array($template)) {
                    $row = [];

                    foreach ($template as $key => $value) {
                        $row[(string) $key] = $value;
                    }

                    $templates[] = $row;
                }
            }

            $next = $response->json('paging.next');
            $url = is_string($next) ? $next : '';
        }

        return $templates;
    }

    public function probe(IntegrationConnection $connection): Response
    {
        return $this->client($connection)->get($this->phonePath($connection).'?fields=display_phone_number,verified_name,quality_rating');
    }

    public function client(IntegrationConnection $connection): PendingRequest
    {
        $version = FieldSpec::string(config('services.whatsapp.graph_version'), 'v25.0');
        $timeout = config('services.whatsapp.timeout');

        return Http::baseUrl(rtrim(FieldSpec::string(config('services.whatsapp.graph_url'), 'https://graph.facebook.com'), '/')."/{$version}/")
            ->withToken(FieldSpec::string($connection->credentials['access_token'] ?? null))
            ->acceptJson()
            ->timeout(is_int($timeout) ? $timeout : 15);
    }

    public static function error(Response $response): string
    {
        $message = $response->json('error.message');

        return mb_substr(is_string($message) ? $message : $response->body(), 0, 500);
    }

    public static function errorCode(Response $response): string
    {
        $code = $response->json('error.code');

        return is_scalar($code) ? (string) $code : (string) $response->status();
    }

    private function phonePath(IntegrationConnection $connection): string
    {
        return FieldSpec::string($connection->config['phone_number_id'] ?? null);
    }
}
