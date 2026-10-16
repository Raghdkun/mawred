<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Engine;

use App\Domain\Workflows\Models\WorkflowInstance;
use Closure;

/**
 * How an approvals-inbox task describes a subject that isn't a request (an offer, D91): a line
 * saying what it is about, a few facts and a link. Domains register a closure per subject class.
 *
 * @phpstan-type Summary array{about: string, facts: list<array{label: string, value: string}>, url: string|null}
 */
final class SubjectSummaries
{
    /** @var array<class-string, Closure(int, string): (array{about: string, facts: list<array{label: string, value: string}>, url: string|null}|null)> */
    private array $summaries = [];

    /**
     * @param  class-string  $subjectClass
     * @param  Closure(int, string): (array{about: string, facts: list<array{label: string, value: string}>, url: string|null}|null)  $summary  subject id, locale
     */
    public function register(string $subjectClass, Closure $summary): void
    {
        $this->summaries[$subjectClass] = $summary;
    }

    /**
     * @return array{about: string, facts: list<array{label: string, value: string}>, url: string|null}|null
     */
    public function for(WorkflowInstance $instance, string $locale): ?array
    {
        $summary = $instance->subject_type === null || $instance->subject_id === null ? null : ($this->summaries[$instance->subject_type] ?? null);

        return $summary instanceof Closure ? $summary($instance->subject_id, $locale) : null;
    }
}
