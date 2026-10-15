<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Integrations\Events\IntegrationHealthChanged;
use App\Domain\Integrations\Health\CognitoHealthCheck;
use App\Domain\Integrations\Health\EmailHealthCheck;
use App\Domain\Integrations\Health\HealthChecks;
use App\Domain\Integrations\Listeners\AlertIntegrationAdmins;
use App\Domain\Integrations\Support\RuntimeMailer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\MailManager;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->app->singleton(function (): HealthChecks {
            $checks = new HealthChecks;
            $checks->register(new EmailHealthCheck);
            $checks->register(new CognitoHealthCheck);

            return $checks;
        });
    }

    public function boot(): void
    {
        $this->app->afterResolving('mail.manager', fn (MailManager $manager) => $this->app->make(RuntimeMailer::class)->apply($manager));

        Event::listen(IntegrationHealthChanged::class, AlertIntegrationAdmins::class);

        // Per URL token: one noisy form can't starve the others; Cognito retries on 429.
        RateLimiter::for('cognito', fn (Request $request): Limit => Limit::perMinute(300)->by('cognito:'.hash('sha256', (string) $request->route('token'))));

        Event::listen(JobProcessing::class, function (): void {
            $manager = $this->app->resolved('mail.manager') ? Mail::getFacadeRoot() : null;

            if ($manager instanceof MailManager) {
                $this->app->make(RuntimeMailer::class)->refreshIfStale($manager);
            }
        });
    }
}
