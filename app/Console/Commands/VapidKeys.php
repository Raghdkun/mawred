<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

#[Signature('mawred:vapid-keys')]
#[Description('Print a new VAPID key pair for web push (D128); put them in the environment, never in git.')]
final class VapidKeys extends Command
{
    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();
        foreach (['VAPID_PUBLIC_KEY' => 'publicKey', 'VAPID_PRIVATE_KEY' => 'privateKey'] as $variable => $key) {
            $this->line(sprintf('%s=%s', $variable, is_string($keys[$key] ?? null) ? $keys[$key] : ''));
        }
        $this->warn('Changing the pair later means every device has to turn push on again.');

        return self::SUCCESS;
    }
}
