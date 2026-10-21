<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Integrations;

use App\Domain\Access\Models\User;
use App\Domain\Forms\Support\FieldSpec;
use App\Domain\Integrations\Actions\CheckIntegrationHealth;
use App\Domain\Integrations\Actions\RotateEmailBounceToken;
use App\Domain\Integrations\Actions\SaveEmailSettings;
use App\Domain\Integrations\Actions\SendTestEmail;
use App\Domain\Integrations\Data\EmailSettingsData;
use App\Domain\Integrations\Data\IntegrationCardData;
use App\Domain\Integrations\Enums\IntegrationStatus;
use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Models\CognitoReceipt;
use App\Domain\Integrations\Models\CognitoSource;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Integrations\Support\Connections;
use App\Domain\Notifications\Actions\AllowEmailAgain;
use App\Domain\Notifications\Models\EmailSuppression;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Integrations hub (handoff 41): a card per connection with its health, last success and
 * failure, and Test / Edit / Rotate / Logs. Each integration has its own settings page.
 */
final class IntegrationsController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('integrations.view');

        return Inertia::render('integrations/index', [
            'cards' => array_map(fn (IntegrationType $type): IntegrationCardData => IntegrationCardData::for($type, $connection = Connections::find($type), $this->detailsFor($type, $connection)), IntegrationType::cases()),
            'can' => ['configure' => Gate::allows('integrations.configure')],
        ]);
    }

    public function test(string $type, CheckIntegrationHealth $check): RedirectResponse
    {
        Gate::authorize('integrations.configure');
        $connection = Connections::find(IntegrationType::tryFrom($type) ?? abort(404));
        abort_unless($connection instanceof IntegrationConnection, 404);

        $status = $check->handle($connection);
        Inertia::flash('toast', $status === IntegrationStatus::Healthy
            ? ['type' => 'success', 'message' => __('Connection works.')]
            : ['type' => 'error', 'message' => __('Connection check failed: :reason', ['reason' => $connection->refresh()->last_error ?? $status->label()])]);

        return back();
    }

    public function email(): Response
    {
        Gate::authorize('integrations.configure');
        $connection = Connections::find(IntegrationType::Email);

        return Inertia::render('integrations/email', [
            'email' => $connection instanceof IntegrationConnection ? [
                'host' => $connection->config['host'] ?? '',
                'port' => $connection->config['port'] ?? 587,
                'security' => $connection->config['security'] ?? 'starttls',
                'username' => $connection->config['username'] ?? null,
                'fromAddress' => $connection->config['from_address'] ?? '',
                'fromName' => $connection->config['from_name'] ?? '',
                'hasPassword' => $connection->masked('password') !== null,
                'status' => $connection->status,
                'lastError' => $connection->last_error,
            ] : null,
            'bounces' => [
                'tokenLast4' => $connection instanceof IntegrationConnection && is_string($connection->config['bounce_token_last4'] ?? null) ? $connection->config['bounce_token_last4'] : null,
                'suppressed' => array_values(EmailSuppression::query()->latest()->limit(100)->get()
                    ->map(fn (EmailSuppression $s): array => ['id' => $s->ulid, 'email' => $s->email, 'reason' => $s->reason, 'detail' => $s->detail, 'at' => $s->created_at->toIso8601String()])->all()),
            ],
        ]);
    }

    public function saveEmail(EmailSettingsData $data, SaveEmailSettings $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('integrations.configure');
        $save->handle(Actor::user($user), $data);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Email settings saved.')]);

        return back();
    }

    public function rotateBounceToken(RotateEmailBounceToken $rotate, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('integrations.configure');
        $connection = Connections::find(IntegrationType::Email);
        abort_unless($connection instanceof IntegrationConnection, 404);
        Inertia::flash('bounceUrl', route('hooks.email.bounces', ['token' => $rotate->handle(Actor::user($user), $connection)]));

        return back();
    }

    public function allowEmail(EmailSuppression $suppression, AllowEmailAgain $allow, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('integrations.configure');
        $allow->handle(Actor::user($user), $suppression);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mawred will email :email again.', ['email' => $suppression->email])]);

        return back();
    }

    public function testEmail(SendTestEmail $send, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('integrations.configure');
        $send->handle($user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test email sent to :email.', ['email' => $user->email])]);

        return back();
    }

    /**
     * @return array<string, string|null>
     */
    private function detailsFor(IntegrationType $type, ?IntegrationConnection $connection): array
    {
        if (! $connection instanceof IntegrationConnection) {
            return [];
        }

        return match ($type) {
            IntegrationType::Email => [
                'server' => FieldSpec::string($connection->config['host'] ?? null).':'.(is_int($connection->config['port'] ?? null) ? $connection->config['port'] : ''),
                'sender' => is_string($connection->config['from_address'] ?? null) ? $connection->config['from_address'] : null,
                'password' => $connection->masked('password'),
            ],
            IntegrationType::WhatsAppOfficial => [
                'phone' => is_string($connection->config['phone_number_id'] ?? null) ? $connection->config['phone_number_id'] : null,
                'token' => $connection->masked('access_token'),
            ],
            IntegrationType::WhatsAppWaha => [
                'url' => is_string($connection->config['base_url'] ?? null) ? $connection->config['base_url'] : null,
                'session' => is_string($connection->config['session'] ?? null) ? $connection->config['session'] : null,
                'apiKey' => $connection->masked('api_key'),
            ],
            IntegrationType::Cognito => [
                'sources' => (string) CognitoSource::query()->where('is_active', true)->count(),
                'received' => (string) CognitoReceipt::query()->where('created_at', '>=', today())->count(),
            ],
        };
    }
}
