<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Contracts;

use App\Domain\Forms\Models\Form;
use App\Domain\Integrations\Models\CognitoSource;

/**
 * A Cognito source can feed a job posting instead of a request type (D81). Hiring implements
 * this so Integrations can hand entries over without depending on the Hiring domain.
 */
interface CognitoApplications
{
    /** The title of the posting the source feeds, or null when it feeds none. */
    public function posting(CognitoSource $source, string $locale): ?string;

    /** The Mawred application form the source's answers map onto, or null when it feeds no posting. */
    public function form(CognitoSource $source): ?Form;

    /**
     * Creates the application, or refreshes it when the candidate applied before.
     *
     * @param  array<string, mixed>  $answers  mapped onto the posting's form, files already stored
     * @return int the application's id
     */
    public function submit(CognitoSource $source, array $answers, string $session): int;
}
