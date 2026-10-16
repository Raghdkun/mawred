<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Setup\Support\Settings;
use App\Support\Actors\Actor;

/** The careers page introduction and the privacy notice applicants agree to (D86, D87). */
final readonly class SaveHiringSettings
{
    public function __construct(private Settings $settings) {}

    /**
     * @param  array{en: string, ar?: string|null}  $careersIntro
     * @param  array{en: string, ar?: string|null}  $privacyNotice
     */
    public function handle(Actor $actor, array $careersIntro, array $privacyNotice): void
    {
        $both = fn (array $text): array => ['en' => $text['en'], 'ar' => ($text['ar'] ?? null) ?: $text['en']];
        $this->settings->set('hiring.careers_intro', $both($careersIntro));
        $this->settings->set('hiring.privacy_notice', $both($privacyNotice));

        activity('hiring')->causedBy($actor->user)->event('settings_changed')->log('Hiring settings changed');
    }
}
