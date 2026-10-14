<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\People\Actions\SendDocumentExpiryReminders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('people:document-reminders')]
#[Description('Notify employees, managers and HR about documents that expire soon')]
final class SendDocumentReminders extends Command
{
    public function handle(SendDocumentExpiryReminders $reminders): int
    {
        $this->components->info($reminders->handle().' reminder(s) sent.');

        return self::SUCCESS;
    }
}
