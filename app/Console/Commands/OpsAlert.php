<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Notifications\Support\EmailSuppressions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;

#[Signature('ops:alert {subject : One line, such as "Disk 85% full"} {details : What happened and where to look}')]
#[Description('Email a server alert to OPS_ALERT_EMAILS (ADR-0019); the host monitor and backup scripts call it.')]
final class OpsAlert extends Command
{
    public function handle(): int
    {
        $configured = config('mawred.ops.alert_emails');
        $recipients = collect(explode(',', is_string($configured) ? $configured : ''))
            ->map(fn (string $email): string => mb_strtolower(trim($email)))
            ->filter(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->reject(fn (string $email): bool => EmailSuppressions::blocks($email))
            ->unique()
            ->values()
            ->all();

        if ($recipients === []) {
            $this->error('OPS_ALERT_EMAILS has no address that can receive email.');

            return self::FAILURE;
        }

        $subject = mb_substr(trim(preg_replace('/\s+/', ' ', $this->stringArgument('subject')) ?? ''), 0, 150);
        $details = trim($this->stringArgument('details'));

        Mail::raw(
            sprintf("%s\n\n%s", $details, config()->string('app.url')),
            fn (Message $message): Message => $message->to($recipients)->subject(sprintf('[Mawred] %s', $subject)),
        );

        $this->info(sprintf('Alert sent to %d address(es).', count($recipients)));

        return self::SUCCESS;
    }

    private function stringArgument(string $name): string
    {
        $value = $this->argument($name);

        return is_string($value) ? $value : '';
    }
}
