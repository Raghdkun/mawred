<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Integrations\Actions\ReceiveCognitoEntry;
use App\Domain\Integrations\Models\CognitoSource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cognito "Post JSON Data to a Website" endpoints (ADR-0015): one URL per event, the secret in
 * the path (Cognito can't send headers). Unknown or rotated tokens answer 404, which Cognito
 * doesn't retry; everything else is stored and acknowledged at once.
 */
final class CognitoHookController extends Controller
{
    public const int MAX_BYTES = 1024 * 1024;

    public function __invoke(Request $request, string $token, string $event, ReceiveCognitoEntry $receive): JsonResponse
    {
        $source = strlen($token) < 40 || strlen($token) > 128 ? null : CognitoSource::query()->where('token_hash', hash('sha256', $token))->first();

        if (! $source instanceof CognitoSource) {
            return response()->json(['error' => ['code' => 'not_found', 'message' => 'Unknown endpoint.']], 404);
        }

        if (strlen($request->getContent()) > self::MAX_BYTES) {
            return response()->json(['error' => ['code' => 'too_large', 'message' => 'Payload too large.']], 413);
        }

        $decoded = json_decode($request->getContent(), true);

        if (! is_array($decoded) || array_is_list($decoded)) {
            return response()->json(['error' => ['code' => 'invalid_json', 'message' => 'Expected a JSON object.']], 400);
        }

        $payload = [];

        foreach ($decoded as $key => $value) {
            $payload[(string) $key] = $value;
        }

        /** @var 'submit'|'update'|'delete' $event */
        $receipt = $receive->handle($source, $event, $payload);

        return response()->json(['received' => $receipt->ulid]);
    }
}
