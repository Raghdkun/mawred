<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Training\Actions\SendCertificateReminders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('training:certificates')]
#[Description('Remind holders of expiring certificates and enrol them to renew (D103).')]
final class TrainingCertificates extends Command
{
    public function handle(SendCertificateReminders $reminders): int
    {
        $this->info('Sent '.$reminders->handle().' reminder(s).');

        return self::SUCCESS;
    }
}
