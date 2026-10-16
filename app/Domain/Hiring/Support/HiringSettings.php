<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Support;

use App\Domain\Forms\Models\Form;
use App\Domain\Setup\Support\Settings;

/**
 * Hiring settings in the `hiring` settings group: the careers page introduction and privacy
 * notice (both bilingual), and the default application form.
 */
final readonly class HiringSettings
{
    public const string DEFAULT_FORM_CODE = 'JOB_APPLICATION';

    public function __construct(private Settings $settings) {}

    /**
     * @return array<string, string>
     */
    public function careersIntro(): array
    {
        return $this->texts('hiring.careers_intro');
    }

    /**
     * @return array<string, string>
     */
    public function privacyNotice(): array
    {
        return $this->texts('hiring.privacy_notice');
    }

    public function defaultForm(): ?Form
    {
        return Form::query()->where('code', self::DEFAULT_FORM_CODE)->first();
    }

    /**
     * @return array<string, string>
     */
    private function texts(string $path): array
    {
        $value = $this->settings->get($path);
        $texts = [];

        foreach (is_array($value) ? $value : [] as $locale => $text) {
            if (is_string($text) && $text !== '') {
                $texts[(string) $locale] = $text;
            }
        }

        return $texts;
    }
}
