<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Integrations\Actions\RecordEmailBounce;
use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Integrations\Support\Connections;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use App\Support\Actors\Channel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Postmark's bounce and spam-complaint webhook (D127). The secret token in the path is the only
 * credential (compared by hash); a wrong or old token answers 404.
 */
final class EmailBounceHookController extends Controller
{
    public function __invoke(Request $request, string $token, RecordEmailBounce $record): Response
    {
        $connection = Connections::find(IntegrationType::Email);
        $expected = $connection instanceof IntegrationConnection ? ($connection->config['bounce_token_hash'] ?? null) : null;

        abort_unless(is_string($expected) && strlen($token) >= 40 && hash_equals($expected, hash('sha256', $token)), 404);

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();
        $record->handle(Actor::integration('postmark', Channel::System), $payload);

        return response()->noContent();
    }
}
