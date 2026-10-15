<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Jobs;

use App\Domain\Integrations\Actions\ProcessCognitoReceipt;
use App\Domain\Integrations\Enums\ReceiptStatus;
use App\Domain\Integrations\Models\CognitoReceipt;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Tries;

/** Processes one Cognito receipt off the webhook path. Failures are recorded on the receipt. */
#[Queue('integrations')]
#[Tries(1)]
final class ProcessCognitoReceiptJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $receiptId)
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return (string) $this->receiptId;
    }

    public function handle(ProcessCognitoReceipt $process): void
    {
        $receipt = CognitoReceipt::query()->find($this->receiptId);

        if ($receipt instanceof CognitoReceipt && $receipt->status === ReceiptStatus::Received) {
            $process->handle($receipt);
        }
    }
}
