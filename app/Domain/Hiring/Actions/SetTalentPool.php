<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Hiring\Models\Candidate;
use App\Support\Actors\Actor;

/**
 * Adds a candidate to the talent pool or takes them out. Only the candidate's own consent extends
 * how long their details are kept (D86); a recruiter's flag does not.
 */
final readonly class SetTalentPool
{
    public function handle(Actor $actor, Candidate $candidate, bool $inPool): Candidate
    {
        $candidate->forceFill(['talent_pool' => $inPool])->save();

        activity('hiring')->causedBy($actor->user)->performedOn($candidate)->event($inPool ? 'pooled' : 'unpooled')
            ->log($inPool ? 'Added to the talent pool' : 'Removed from the talent pool');

        return $candidate;
    }
}
