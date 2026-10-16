<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Support;

use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Setup\Support\Settings;
use App\Support\Errors\DomainRuleViolation;
use App\Support\Templates\SafeTemplate;
use InvalidArgumentException;

/**
 * Admin-written messages to candidates (stage messages, the rejection note, D93): plain text with
 * `{{ candidate_name }}`, `{{ job_title }}`, `{{ stage }}` and `{{ company }}`, rendered through
 * SafeTemplate in the candidate's language.
 */
final readonly class CandidateTemplates
{
    public const array VARIABLES = ['candidate_name', 'job_title', 'stage', 'company'];

    public function __construct(private SafeTemplate $templates, private Settings $settings) {}

    /**
     * @param  list<string>  $texts
     */
    public function assertValid(array $texts, string $field): void
    {
        foreach ($texts as $text) {
            try {
                $this->templates->assertValid($text, self::VARIABLES);
            } catch (InvalidArgumentException) {
                $message = __('Use only these variables: :list.', ['list' => implode(', ', array_map(fn (string $v): string => '{{ '.$v.' }}', self::VARIABLES))]);

                throw new DomainRuleViolation('hiring.template', $message, [$field => [$message]]);
            }
        }
    }

    /**
     * @param  array<string, string>  $text  bilingual template
     */
    public function render(array $text, Candidate $candidate, JobApplication $application): string
    {
        $locale = $candidate->locale === 'ar' ? 'ar' : 'en';

        return $this->templates->render(FieldSpec::text($text, $locale), [
            'candidate_name' => $candidate->name,
            'job_title' => Texts::one($application->requisition()->first(), 'title', $locale),
            'stage' => Texts::one($application->stage()->first(), 'name', $locale),
            'company' => FieldSpec::text($this->settings->get('company.name'), $locale, FieldSpec::string(config('app.name'), 'Mawred')),
        ], $locale, escapeHtml: false);
    }
}
