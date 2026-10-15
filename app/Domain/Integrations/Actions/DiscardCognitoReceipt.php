<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Integrations\Enums\ReceiptStatus;
use App\Domain\Integrations\Models\CognitoReceipt;
use App\Support\Actors\Actor;
use App\Support\Errors\DomainRuleViolation;

/** Sets an entry aside for good (kept in the log, never processed). */
final readonly class DiscardCognitoReceipt
{
    public function handle(Actor $actor, CognitoReceipt $receipt): CognitoReceipt
    {
        if ($receipt->status === ReceiptStatus::Processed) {
            throw new DomainRuleViolation('integrations.receipt_processed', __('This entry already became a request.'));
        }

        $receipt->forceFill(['status' => ReceiptStatus::Discarded])->save();
        activity('integrations')->causedBy($actor->user)->event('cognito.discarded')->withProperties(['receipt' => $receipt->ulid])->log('Cognito entry discarded');

        return $receipt;
    }
}
