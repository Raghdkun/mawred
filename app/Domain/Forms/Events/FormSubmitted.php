<?php

declare(strict_types=1);

namespace App\Domain\Forms\Events;

use App\Domain\Forms\Models\FormSubmission;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Dispatched after a submission is committed (request types and workflows listen in 3b). */
final readonly class FormSubmitted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public FormSubmission $submission) {}
}
