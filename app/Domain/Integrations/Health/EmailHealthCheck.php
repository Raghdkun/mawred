<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Health;

use App\Domain\Integrations\Contracts\HealthCheck;
use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Integrations\Support\RuntimeMailer;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Throwable;

/** Opens an SMTP session (connect, TLS, auth) and closes it; nothing is sent. */
final class EmailHealthCheck implements HealthCheck
{
    public function type(): IntegrationType
    {
        return IntegrationType::Email;
    }

    public function check(IntegrationConnection $connection): HealthResult
    {
        config(['mail.mailers.mawred_health' => RuntimeMailer::transport($connection)]);
        $manager = Mail::getFacadeRoot();

        if (! $manager instanceof MailManager) {
            return HealthResult::healthy();
        }

        $manager->purge('mawred_health');
        $transport = $manager->mailer('mawred_health')->getSymfonyTransport();

        if (! $transport instanceof SmtpTransport) {
            return HealthResult::healthy();
        }

        try {
            $transport->start();
            $transport->stop();
        } catch (Throwable $e) {
            return HealthResult::disconnected(mb_substr($e->getMessage(), 0, 500));
        }

        return HealthResult::healthy();
    }
}
