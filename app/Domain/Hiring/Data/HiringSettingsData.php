<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Hiring settings: careers texts (D87), retention periods (D86), the rejection message (D93) and
 * the offer letter templates (D95). Sections left out are kept as they are.
 */
#[TypeScript]
final class HiringSettingsData extends Data
{
    /**
     * @param  array{en: string, ar?: string|null}  $careersIntro
     * @param  array{en: string, ar?: string|null}  $privacyNotice
     * @param  array{enabled: bool, subject: array{en: string, ar?: string|null}, body: array{en: string, ar?: string|null}}|null  $rejectionMessage
     * @param  array{en: string, ar?: string|null}|null  $offerLetter
     */
    public function __construct(
        public array $careersIntro,
        public array $privacyNotice,
        public ?int $retentionMonths = null,
        public ?int $poolRetentionMonths = null,
        public ?array $rejectionMessage = null,
        public ?array $offerLetter = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'careersIntro.en' => ['required', 'string', 'max:600'],
            'careersIntro.ar' => ['nullable', 'string', 'max:600'],
            'privacyNotice.en' => ['required', 'string', 'max:2000'],
            'privacyNotice.ar' => ['nullable', 'string', 'max:2000'],
            'retentionMonths' => ['nullable', 'integer', 'between:1,120'],
            'poolRetentionMonths' => ['nullable', 'integer', 'between:1,120', 'gte:retentionMonths'],
            'rejectionMessage' => ['nullable', 'array'],
            'rejectionMessage.enabled' => ['required_with:rejectionMessage', 'boolean'],
            'rejectionMessage.subject.en' => ['required_if:rejectionMessage.enabled,true', 'nullable', 'string', 'max:200'],
            'rejectionMessage.subject.ar' => ['nullable', 'string', 'max:200'],
            'rejectionMessage.body.en' => ['required_if:rejectionMessage.enabled,true', 'nullable', 'string', 'max:4000'],
            'rejectionMessage.body.ar' => ['nullable', 'string', 'max:4000'],
            'offerLetter' => ['nullable', 'array'],
            'offerLetter.en' => ['required_with:offerLetter', 'string', 'max:20000'],
            'offerLetter.ar' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
