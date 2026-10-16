<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Support;

use App\Domain\Access\Models\User;
use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\JobApplication;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/**
 * Interview panels (D92): anyone on an interview's panel sees that candidate (profile, CV, their
 * interview) whatever their role. Deliberately outside the scope resolver: it is not a grant,
 * just a seat on one interview.
 */
final readonly class PanelAccess
{
    public function __construct(private ConnectionInterface $db) {}

    public function onApplication(User $user, JobApplication $application): bool
    {
        return $this->applicationIds($user)->where('i.job_application_id', $application->id)->exists();
    }

    public function onCandidate(User $user, Candidate $candidate): bool
    {
        return $this->db->table('job_applications')->where('candidate_id', $candidate->id)
            ->whereIn('id', $this->applicationIds($user))
            ->exists();
    }

    /** Applications with an interview the user is on the panel of. */
    public function applicationIds(User $user): Builder
    {
        return $this->db->table('interviews as i')
            ->select('i.job_application_id')
            ->join('interview_panelists as p', 'p.interview_id', '=', 'i.id')
            ->where('p.user_id', $user->id);
    }
}
