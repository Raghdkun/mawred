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

    /** Months after the last activity before a candidate is anonymised (D86, D96). */
    public function retentionMonths(): int
    {
        return $this->months('hiring.retention_months', 12);
    }

    /** The same for candidates who agreed to the talent pool. */
    public function poolRetentionMonths(): int
    {
        return $this->months('hiring.pool_retention_months', 24);
    }

    /**
     * The thank-you message rejections can send (D93), or null when switched off.
     *
     * @return array{subject: array<string, string>, body: array<string, string>}|null
     */
    public function rejectionMessage(): ?array
    {
        $value = $this->settings->get('hiring.rejection_message');

        if (! is_array($value) || ($value['enabled'] ?? false) !== true) {
            return null;
        }

        $pair = function (mixed $text): array {
            $texts = [];

            foreach (['en', 'ar'] as $locale) {
                $line = is_array($text) ? ($text[$locale] ?? null) : null;

                if (is_string($line) && $line !== '') {
                    $texts[$locale] = $line;
                }
            }

            return $texts;
        };

        return ['subject' => $pair($value['subject'] ?? null), 'body' => $pair($value['body'] ?? null)];
    }

    /**
     * @return array{enabled: bool, subject: array{en: string, ar: string}, body: array{en: string, ar: string}}
     */
    public function rejectionMessageForEditing(): array
    {
        $value = $this->settings->get('hiring.rejection_message');
        $value = is_array($value) ? $value : [];
        $pair = fn (mixed $text): array => ['en' => is_array($text) && is_string($text['en'] ?? null) ? $text['en'] : '', 'ar' => is_array($text) && is_string($text['ar'] ?? null) ? $text['ar'] : ''];

        return ['enabled' => ($value['enabled'] ?? false) === true, 'subject' => $pair($value['subject'] ?? null), 'body' => $pair($value['body'] ?? null)];
    }

    public function defaultForm(): ?Form
    {
        return Form::query()->where('code', self::DEFAULT_FORM_CODE)->first();
    }

    private function months(string $path, int $default): int
    {
        $value = $this->settings->get($path);

        return is_int($value) && $value >= 1 ? $value : $default;
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
