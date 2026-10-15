<?php

declare(strict_types=1);

namespace App\Domain\Leave\Actions;

use App\Domain\Leave\Models\LeaveBlackout;
use App\Support\Actors\Actor;

/** Removes a blackout period; the audit log keeps what it was. */
final readonly class DeleteLeaveBlackout
{
    public function handle(Actor $actor, LeaveBlackout $blackout): void
    {
        activity('leave')->causedBy($actor->user)->performedOn($blackout)->event('blackout.deleted')
            ->withProperties(['from' => $blackout->starts_on->toDateString(), 'to' => $blackout->ends_on->toDateString()])->log('Leave blackout removed');

        $blackout->delete();
    }
}
