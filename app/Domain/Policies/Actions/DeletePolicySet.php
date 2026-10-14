<?php

declare(strict_types=1);

namespace App\Domain\Policies\Actions;

use App\Domain\Policies\Models\PolicySet;
use App\Support\Errors\DomainRuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * Archives a set nobody uses any more. Its history stays for past dates.
 */
final readonly class DeletePolicySet
{
    public function handle(PolicySet $set): void
    {
        $today = CarbonImmutable::now('UTC')->toDateString();

        if ($set->assignments()->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $today))->exists()) {
            throw new DomainRuleViolation('policies.set_in_use', __('This set is still assigned. End its assignments first.'));
        }

        $set->delete();
    }
}
