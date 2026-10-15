<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Integrations\Models\CognitoReceipt;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

/** Runs a failed, ignored or sample entry again, e.g. after fixing the mapping. */
final readonly class ReplayCognitoReceipt
{
    public function __construct(private ProcessCognitoReceipt $process) {}

    public function handle(Actor $actor, CognitoReceipt $receipt): CognitoReceipt
    {
        if (! $receipt->status->canReplay()) {
            throw new DomainRuleViolation('integrations.receipt_not_replayable', __('This entry can’t be run again.'));
        }

        activity('integrations')->causedBy($actor->user)->event('cognito.replayed')->withProperties(['receipt' => $receipt->ulid])->log('Cognito entry replayed');

        return $this->process->handle($receipt);
    }
}
