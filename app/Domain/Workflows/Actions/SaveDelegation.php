<?php

declare(strict_types=1);

namespace App\Domain\Workflows\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Workflows\Models\Delegation;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

/**
 * "While I'm away" (ADR-0009): new approvals for the person go to someone else for a date range.
 * Tasks already in the inbox stay put; they can be delegated by hand.
 */
final readonly class SaveDelegation
{
    public function handle(Actor $actor, User $from, User $to, string $startsOn, string $endsOn, ?string $reason = null): Delegation
    {
        if ($from->is($to)) {
            throw new DomainRuleViolation('workflows.delegate_self', __('Choose someone else.'), ['to' => [__('Choose someone else.')]]);
        }

        if ($endsOn < $startsOn) {
            throw new DomainRuleViolation('workflows.dates_reversed', __('The end date must be on or after the start date.'), ['endsOn' => [__('The end date must be on or after the start date.')]]);
        }

        if (Delegation::query()->where('from_user_id', $to->id)->where('to_user_id', $from->id)->where('starts_on', '<=', $endsOn)->where('ends_on', '>=', $startsOn)->exists()) {
            throw new DomainRuleViolation('workflows.delegation_loop', __('They are delegating to you for some of these dates.'), ['to' => [__('They are delegating to you for some of these dates.')]]);
        }

        return Delegation::query()->create([
            'from_user_id' => $from->id,
            'to_user_id' => $to->id,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'reason' => $reason,
            'created_by' => $actor->user?->id,
        ]);
    }
}
