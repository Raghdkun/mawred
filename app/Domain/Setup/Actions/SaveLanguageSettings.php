<?php

declare(strict_types=1);

namespace App\Domain\Setup\Actions;

use App\Domain\Setup\Support\Settings;
use App\Support\Actors\Actor;

/**
 * Wizard step 3 (D3): EN + AR are always available; this sets the defaults new users inherit.
 */
final readonly class SaveLanguageSettings
{
    public function __construct(private Settings $settings) {}

    public function handle(Actor $actor, string $defaultLocale, string $defaultDigits, bool $hijriByDefault): void
    {
        $this->settings->set('locales.default', $defaultLocale);
        $this->settings->set('locales.default_digits', $defaultDigits);
        $this->settings->set('locales.hijri_default', $hijriByDefault);

        activity('setup')->causedBy($actor->user)->event('languages.saved')
            ->withProperties(['default' => $defaultLocale, 'digits' => $defaultDigits, 'hijri' => $hijriByDefault])
            ->log('Language defaults saved');
    }
}
