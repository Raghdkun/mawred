<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Integrations\Models\CognitoSource;
use App\Support\Actors\Actor;

/**
 * Issues new webhook URLs for a source; the old ones stop working at once (Cognito gets 404
 * and doesn't retry). The token is returned once and audited without its value.
 */
final readonly class RotateCognitoToken
{
    public function handle(Actor $actor, CognitoSource $source): string
    {
        $token = SaveCognitoSource::newToken();
        $source->forceFill(['token_hash' => hash('sha256', $token), 'token_last4' => substr($token, -4), 'rotated_at' => now()])->save();

        activity('integrations')->causedBy($actor->user)->performedOn($source)->event('credentials.rotated')->withProperties(['keys' => ['token']])->log('Cognito URLs rotated');

        return $token;
    }
}
