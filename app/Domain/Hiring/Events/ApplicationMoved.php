<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Events;

use App\Domain\Hiring\Models\JobApplication;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/** A candidate changed stage; stage automations (D93) run from here after the commit. */
final readonly class ApplicationMoved implements ShouldDispatchAfterCommit
{
    public function __construct(public JobApplication $application, public int $fromStageId, public int $toStageId, public ?int $actorUserId = null) {}
}
