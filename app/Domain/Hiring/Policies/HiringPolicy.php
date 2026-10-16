<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Policies;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Models\JobApplication;
use App\Domain\Hiring\Models\JobPosting;
use App\Domain\Hiring\Models\JobRequisition;
use App\Domain\Hiring\Support\HiringScope;

/**
 * Record-level hiring access (ADR-0005, D82): `hiring.view` to look, `hiring.update` to act, each
 * over the requisitions the viewer's grant covers. Out-of-scope records answer 404 upstream.
 */
final readonly class HiringPolicy
{
    public function __construct(private ScopeResolver $resolver, private HiringScope $scope) {}

    public function viewRequisition(User $user, JobRequisition $requisition): bool
    {
        return $this->resolver->allows($user, 'hiring.view', $requisition, $this->scope->requisitions());
    }

    public function updateRequisition(User $user, JobRequisition $requisition): bool
    {
        return $this->resolver->allows($user, 'hiring.update', $requisition, $this->scope->requisitions());
    }

    public function updatePosting(User $user, JobPosting $posting): bool
    {
        $requisition = $posting->requisition()->first();

        return $requisition instanceof JobRequisition && $this->updateRequisition($user, $requisition);
    }

    public function viewApplication(User $user, JobApplication $application): bool
    {
        return $this->resolver->allows($user, 'hiring.view', $application, $this->scope->applications());
    }

    public function updateApplication(User $user, JobApplication $application): bool
    {
        return $this->resolver->allows($user, 'hiring.update', $application, $this->scope->applications());
    }

    public function viewCandidate(User $user, Candidate $candidate): bool
    {
        return $candidate->merged_into_id === null && $this->resolver->allows($user, 'hiring.view', $candidate, $this->scope->candidates());
    }

    public function updateCandidate(User $user, Candidate $candidate): bool
    {
        return $candidate->merged_into_id === null && $this->resolver->allows($user, 'hiring.update', $candidate, $this->scope->candidates());
    }
}
