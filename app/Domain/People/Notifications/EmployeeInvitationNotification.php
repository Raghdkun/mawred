<?php

declare(strict_types=1);

namespace App\Domain\People\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invitation email with the one-time link. Sent on demand to an address (the person has no
 * account yet), in the language HR chose for them.
 */
final class EmployeeInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $url,
        public readonly string $employeeName,
        public readonly string $companyName,
        public readonly int $validDays,
        public readonly string $mailLocale,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $replace = ['name' => $this->employeeName, 'company' => $this->companyName, 'days' => $this->validDays];

        return (new MailMessage)
            ->subject($this->line('Your :company account', $replace))
            ->greeting($this->line('Hello :name,', $replace))
            ->line($this->line(':company invited you to Mawred, where you will find your requests, leave, schedule and documents.', $replace))
            ->action($this->line('Create my account', $replace), $this->url)
            ->line($this->line('This link works once and expires in :days days.', $replace));
    }

    /**
     * @param  array<string, int|string>  $replace
     */
    private function line(string $key, array $replace): string
    {
        $text = __($key, $replace, $this->mailLocale);

        return is_string($text) ? $text : $key;
    }
}
