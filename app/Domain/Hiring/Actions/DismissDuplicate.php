<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Hiring\Models\Candidate;
use App\Support\Actors\Actor;

/** A recruiter checked a possible duplicate and it is a different person. */
final readonly class DismissDuplicate
{
    public function handle(Actor $actor, Candidate $candidate): Candidate
    {
        $candidate->forceFill(['possible_duplicate_of_id' => null])->save();

        activity('hiring')->causedBy($actor->user)->performedOn($candidate)->event('duplicate_dismissed')->log('Not a duplicate');

        return $candidate;
    }
}
