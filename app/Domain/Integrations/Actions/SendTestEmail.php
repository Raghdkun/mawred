<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Integrations\Mail\TestEmail;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Integrations\Support\RuntimeMailer;
use App\Support\Errors\DomainRuleViolation;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends a test message through the saved connection, right now, to the admin. The outcome
 * becomes the connection's status so the hub (Phase 4) can show it.
 */
final readonly class SendTestEmail
{
    public function handle(User $to): void
    {
        $connection = RuntimeMailer::connection();

        if (! $connection instanceof IntegrationConnection) {
            throw new DomainRuleViolation('integrations.email_missing', __('Save the email settings first.'));
        }

        config(['mail.mailers.mawred_test' => RuntimeMailer::transport($connection)]);
        $mail = Mail::getFacadeRoot();

        if ($mail instanceof MailManager) {
            $mail->purge('mawred_test');
        }

        try {
            Mail::mailer('mawred_test')->to($to->email)->send((new TestEmail($to->locale === 'ar' ? 'ar' : 'en'))
                ->from(FieldSpec::string($connection->config['from_address'] ?? null), FieldSpec::string($connection->config['from_name'] ?? null)));
        } catch (Throwable $e) {
            $connection->forceFill(['status' => 'disconnected', 'last_failure_at' => now(), 'last_error' => mb_substr($e->getMessage(), 0, 500)])->save();

            throw new DomainRuleViolation('integrations.email_failed', __('The test email could not be sent: :reason', ['reason' => mb_substr($e->getMessage(), 0, 200)]));
        }

        $connection->forceFill(['status' => 'healthy', 'last_success_at' => now(), 'last_error' => null])->save();
    }
}
