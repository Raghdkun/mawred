<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Integrations\Actions\CheckIntegrationHealth;
use App\Domain\Integrations\Actions\HandleWhatsAppReply;
use App\Domain\Integrations\Actions\RecordWhatsAppStatus;
use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Integrations\Support\Connections;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * WhatsApp webhooks (ADR-0015). Official: the GET handshake with our verify token, and POSTs
 * signed with X-Hub-Signature-256 (HMAC-SHA256 of the raw body, app secret). WAHA: POSTs signed
 * with X-Webhook-Hmac (HMAC-SHA512). Unsigned or wrongly signed calls get 401. Meta retries for
 * up to 36 hours, so messages and statuses are de-duplicated by id.
 */
final class WhatsAppHookController extends Controller
{
    public function verify(Request $request): Response
    {
        $connection = Connections::find(IntegrationType::WhatsAppOfficial);
        $expected = FieldSpec::string($connection?->credentials['verify_token'] ?? null);
        $given = $request->query('hub_verify_token', $request->query('hub.verify_token'));

        if ($expected === '' || $request->query('hub_mode', $request->query('hub.mode')) !== 'subscribe' || ! is_string($given) || ! hash_equals($expected, $given)) {
            return response('Forbidden', 403);
        }

        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        return response(is_string($challenge) ? $challenge : '', 200, ['Content-Type' => 'text/plain']);
    }

    public function official(Request $request, HandleWhatsAppReply $reply, RecordWhatsAppStatus $status): JsonResponse
    {
        $connection = Connections::find(IntegrationType::WhatsAppOfficial);
        $secret = FieldSpec::string($connection?->credentials['app_secret'] ?? null);
        $signature = (string) $request->header('X-Hub-Signature-256', '');

        if ($secret === '' || ! hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $secret), $signature)) {
            return response()->json(['error' => ['code' => 'invalid_signature', 'message' => 'Invalid signature.']], 401);
        }

        foreach (Arr::wrap($request->input('entry')) as $entry) {
            foreach (is_array($entry) ? Arr::wrap($entry['changes'] ?? []) : [] as $change) {
                $value = is_array($change) && is_array($change['value'] ?? null) ? $change['value'] : [];

                foreach (Arr::wrap($value['statuses'] ?? []) as $item) {
                    if (is_array($item) && is_string($item['id'] ?? null) && is_string($item['status'] ?? null) && $this->first('status:'.$item['id'].':'.$item['status'])) {
                        $errors = is_array($item['errors'] ?? null) ? $item['errors'] : [];
                        $error = is_array($errors[0] ?? null) ? $errors[0] : [];
                        $status->handle($item['id'], $item['status'], isset($error['code']) && is_scalar($error['code']) ? (string) $error['code'] : null, is_string($error['title'] ?? null) ? $error['title'] : null);
                    }
                }

                foreach (Arr::wrap($value['messages'] ?? []) as $message) {
                    if (! is_array($message) || ! is_string($message['id'] ?? null) || ! is_string($message['from'] ?? null) || ! $this->first('message:'.$message['id'])) {
                        continue;
                    }

                    $payload = Arr::get($message, 'button.payload') ?? Arr::get($message, 'interactive.button_reply.id');
                    $reply->handle(IntegrationType::WhatsAppOfficial, $message['from'], is_string($payload) ? $payload : null);
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    public function waha(Request $request, HandleWhatsAppReply $reply, CheckIntegrationHealth $health): JsonResponse
    {
        $connection = Connections::find(IntegrationType::WhatsAppWaha);
        $key = FieldSpec::string($connection?->credentials['webhook_key'] ?? null);

        if (! $connection instanceof IntegrationConnection || $key === '' || ! hash_equals(hash_hmac('sha512', $request->getContent(), $key), (string) $request->header('X-Webhook-Hmac', ''))) {
            return response()->json(['error' => ['code' => 'invalid_signature', 'message' => 'Invalid signature.']], 401);
        }

        $event = $request->input('event');
        $id = $request->input('id');

        if (is_string($id) && ! $this->first('waha:'.$id)) {
            return response()->json(['ok' => true]);
        }

        if ($event === 'session.status') {
            $health->handle($connection);
        }

        if ($event === 'message' && $request->input('payload.fromMe') !== true && is_string($from = $request->input('payload.from')) && str_ends_with($from, '@c.us')) {
            $reply->handle(IntegrationType::WhatsAppWaha, strstr($from, '@', true) ?: $from, null);
        }

        return response()->json(['ok' => true]);
    }

    /** True the first time an id is seen (36-hour retries from Meta). */
    private function first(string $key): bool
    {
        return Cache::add('whatsapp-hook:'.hash('sha256', $key), true, now()->addDays(2));
    }
}
