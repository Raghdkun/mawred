<?php

declare(strict_types=1);

use App\Domain\Notifications\Models\EmailSuppression;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Symfony\Component\Mime\Address;

/*
| ADR-0019: the host scripts email server alerts through `ops:alert`, to OPS_ALERT_EMAILS minus
| addresses on the do-not-email list (D127).
*/
it('emails the ops addresses that can receive email', function (): void {
    EmailSuppression::query()->create(['email' => 'bounced@example.com', 'reason' => 'hard_bounce', 'source' => 'postmark']);
    config(['mawred.ops.alert_emails' => 'IT@example.com, bounced@example.com, not-an-email, it@example.com']);
    Event::fake([MessageSent::class]);

    $this->artisan('ops:alert', ['subject' => "Disk 85%\nfull", 'details' => '/dev/sda1 85% used'])
        ->expectsOutputToContain('Alert sent to 1 address(es).')
        ->assertSuccessful();

    Event::assertDispatched(MessageSent::class, function (MessageSent $event): bool {
        $message = $event->message;

        return array_map(fn (Address $address): string => $address->getAddress(), $message->getTo()) === ['it@example.com']
            && $message->getSubject() === '[Mawred] Disk 85% full'
            && str_contains((string) $message->getTextBody(), '/dev/sda1 85% used');
    });
});

it('fails when no address can receive the alert', function (): void {
    config(['mawred.ops.alert_emails' => null]);

    $this->artisan('ops:alert', ['subject' => 'Disk full', 'details' => '…'])->assertFailed();
});
