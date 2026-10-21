<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Integrations\Models\IntegrationConnection;
use App\Support\Actors\Actor;
use Illuminate\Support\Str;

/**
 * Issues the bounce-webhook URL for the email connection (D127); an old URL stops working at once.
 * The token is returned once, kept only as a hash, and audited without its value.
 */
final readonly class RotateEmailBounceToken
{
    public function handle(Actor $actor, IntegrationConnection $connection): string
    {
        $token = Str::random(48);
        $connection->forceFill(['config' => [...$connection->config, 'bounce_token_hash' => hash('sha256', $token), 'bounce_token_last4' => substr($token, -4)]])->save();

        activity('integrations')->causedBy($actor->user)->performedOn($connection)->event('credentials.rotated')->withProperties(['keys' => ['bounce_token']])->log('Email bounce webhook URL issued');

        return $token;
    }
}
