<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Integrations\Support\RuntimeMailer;
use Illuminate\Mail\MailManager;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

/**
 * Integrations wiring (ADR-0015): the vault-backed `mawred` mailer, applied lazily and refreshed
 * in queue workers when the email settings change.
 */
final class IntegrationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RuntimeMailer::class);
    }

    public function boot(): void
    {
        $this->app->afterResolving('mail.manager', fn (MailManager $manager) => $this->app->make(RuntimeMailer::class)->apply($manager));

        Event::listen(JobProcessing::class, function (): void {
            $manager = $this->app->resolved('mail.manager') ? Mail::getFacadeRoot() : null;

            if ($manager instanceof MailManager) {
                $this->app->make(RuntimeMailer::class)->refreshIfStale($manager);
            }
        });
    }
}
