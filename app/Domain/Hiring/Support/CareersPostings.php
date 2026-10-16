<?php

declare(strict_types=1);

namespace App\Domain\Hiring\Support;

use App\Domain\Hiring\Enums\PostingStatus;
use App\Domain\Hiring\Enums\RequisitionStatus;
use App\Domain\Hiring\Models\JobPosting;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * What the public careers site lists (D87): published postings of open requisitions whose
 * closing date has not passed. Deliberately unscoped: it is public.
 */
final class CareersPostings
{
    /**
     * @return Builder<JobPosting>
     */
    public static function open(CarbonImmutable $today): Builder
    {
        return JobPosting::query()
            ->where('job_postings.status', PostingStatus::Published->value)
            ->whereHas('requisition', fn (Builder $q) => $q->where('status', RequisitionStatus::Open->value))
            ->where(fn (Builder $q) => $q->whereNull('job_postings.closes_on')->orWhere('job_postings.closes_on', '>=', $today->toDateString()));
    }
}
