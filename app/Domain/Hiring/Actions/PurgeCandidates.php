<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Actions;

use App\Domain\Hiring\Enums\ApplicationStatus;
use App\Domain\Hiring\Enums\OfferStatus;
use App\Domain\Hiring\Models\Candidate;
use App\Domain\Hiring\Support\HiringSettings;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The nightly retention purge (D86, D96): candidates with no open or successful application whose
 * last activity is older than the retention period (longer for those who agreed to the talent
 * pool) are anonymised. Safe to run again.
 */
final readonly class PurgeCandidates
{
    public function __construct(private HiringSettings $settings, private AnonymiseCandidate $anonymise) {}

    public function handle(CarbonImmutable $now): int
    {
        $regular = $now->subMonths($this->settings->retentionMonths());
        $pool = $now->subMonths($this->settings->poolRetentionMonths());
        $count = 0;

        Candidate::query()
            ->whereNull('anonymised_at')
            ->whereNull('merged_into_id')
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $q) => $q->whereNull('talent_pool_consented_at')->where('last_activity_at', '<', $regular))
                ->orWhere(fn (Builder $q) => $q->whereNotNull('talent_pool_consented_at')->where('last_activity_at', '<', $pool)))
            ->whereDoesntHave('applications', fn (Builder $q) => $q->whereIn('status', [ApplicationStatus::Active->value, ApplicationStatus::Hired->value]))
            ->whereDoesntHave('applications.offers', fn (Builder $q) => $q->where('status', OfferStatus::Accepted->value))
            ->orderBy('id')
            ->chunkById(200, function ($candidates) use (&$count): void {
                foreach ($candidates as $candidate) {
                    try {
                        $this->anonymise->handle(Actor::system('hiring:retention'), $candidate, 'retention');
                        $count++;
                    } catch (DomainRuleViolation) {
                        // Hired in the meantime.
                    }
                }
            });

        return $count;
    }
}
