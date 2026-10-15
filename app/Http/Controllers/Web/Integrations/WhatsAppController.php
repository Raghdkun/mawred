<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Integrations;

use App\Domain\Access\Models\User;
use App\Domain\Integrations\Actions\CheckIntegrationHealth;
use App\Domain\Integrations\Actions\InviteToWhatsApp;
use App\Domain\Integrations\Actions\SaveWhatsAppRouting;
use App\Domain\Integrations\Actions\SaveWhatsAppSettings;
use App\Domain\Integrations\Actions\SendWhatsAppTest;
use App\Domain\Integrations\Actions\StartWahaSession;
use App\Domain\Integrations\Actions\SyncWhatsAppTemplates;
use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Integrations\Models\WhatsAppContact;
use App\Domain\Integrations\Models\WhatsAppMessage;
use App\Domain\Integrations\Models\WhatsAppTemplate;
use App\Domain\Integrations\Support\Connections;
use App\Domain\Integrations\WhatsApp\WahaDriver;
use App\Domain\Integrations\WhatsApp\WhatsAppRouting;
use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Setup\Support\Settings;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * WhatsApp setup (handoff 40): driver choice, Official credentials with the webhook URL and
 * verify token, template sync, WAHA sidecar and QR pairing, routing per category with the
 * critical-category warning, test sends and activation invites.
 */
final class WhatsAppController extends Controller
{
    public function show(WhatsAppRouting $routing, Settings $settings, #[CurrentUser] User $user): Response
    {
        Gate::authorize('integrations.configure');
        $official = Connections::find(IntegrationType::WhatsAppOfficial);
        $waha = Connections::find(IntegrationType::WhatsAppWaha);
        $chains = $routing->all();

        return Inertia::render('integrations/whatsapp', [
            'official' => $official instanceof IntegrationConnection ? [
                'phoneNumberId' => $official->config['phone_number_id'] ?? '',
                'wabaId' => $official->config['waba_id'] ?? '',
                'accessToken' => $official->masked('access_token'),
                'appSecret' => $official->masked('app_secret'),
                'verifyToken' => $official->credentials['verify_token'] ?? null,
                'status' => $official->status,
                'lastError' => $official->last_error,
            ] : null,
            'waha' => $waha instanceof IntegrationConnection ? [
                'baseUrl' => $waha->config['base_url'] ?? '',
                'session' => $waha->config['session'] ?? 'default',
                'apiKey' => $waha->masked('api_key'),
                'status' => $waha->status,
                'lastError' => $waha->last_error,
            ] : null,
            'webhooks' => ['official' => route('hooks.whatsapp.official'), 'waha' => route('hooks.whatsapp.waha')],
            'templates' => WhatsAppTemplate::query()->orderBy('name')->orderBy('language')->get()->map(fn (WhatsAppTemplate $t): array => [
                'name' => $t->name, 'language' => $t->language, 'status' => $t->status, 'category' => $t->category,
                'params' => $t->bodyParameters(), 'buttons' => $t->quickReplies(), 'syncedAt' => $t->synced_at->toIso8601String(),
            ])->values(),
            'routing' => collect(NotificationCategory::cases())->map(fn (NotificationCategory $c): array => [
                'category' => $c->value, 'critical' => $c->isCritical(), 'chain' => $chains[$c->value], 'risky' => WhatsAppRouting::riskyCritical($c, $chains[$c->value]),
            ])->values(),
            'verificationTemplate' => $settings->get('whatsapp.verification_template'),
            'myNumber' => WhatsAppContact::query()->where('user_id', $user->id)->whereNotNull('verified_at')->value('phone_e164'),
            'recent' => WhatsAppMessage::query()->latest('created_at')->limit(20)->get()->map(fn (WhatsAppMessage $m): array => [
                'id' => $m->ulid, 'driver' => $m->driver, 'purpose' => $m->purpose, 'to' => $m->phone_last4 === null ? null : '••••'.$m->phone_last4,
                'status' => $m->status, 'error' => $m->error, 'at' => $m->created_at->toIso8601String(),
            ])->values(),
        ]);
    }

    public function saveOfficial(Request $request, SaveWhatsAppSettings $save, CheckIntegrationHealth $check, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('integrations.configure');
        $request->validate([
            'phoneNumberId' => ['required', 'string', 'regex:/^\d{5,30}$/'],
            'wabaId' => ['required', 'string', 'regex:/^\d{5,30}$/'],
            'accessToken' => ['nullable', 'string', 'max:1000'],
            'appSecret' => ['nullable', 'string', 'max:200'],
        ]);

        $connection = $save->handle(Actor::user($user), IntegrationType::WhatsAppOfficial,
            ['phone_number_id' => $request->string('phoneNumberId')->toString(), 'waba_id' => $request->string('wabaId')->toString()],
            ['access_token' => $this->optional($request, 'accessToken'), 'app_secret' => $this->optional($request, 'appSecret')],
        );

        if (($connection->credentials['access_token'] ?? null) !== null) {
            $check->handle($connection);
        }

        return back();
    }

    public function saveWaha(Request $request, SaveWhatsAppSettings $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('integrations.configure');
        $request->validate([
            'baseUrl' => ['required', 'url:http,https', 'max:300'],
            'session' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9_-]+$/'],
            'apiKey' => ['nullable', 'string', 'max:300'],
        ]);

        $save->handle(Actor::user($user), IntegrationType::WhatsAppWaha,
            ['base_url' => $request->string('baseUrl')->toString(), 'session' => $request->string('session')->toString()],
            ['api_key' => $this->optional($request, 'apiKey')],
        );

        return back();
    }

    public function sync(SyncWhatsAppTemplates $sync): RedirectResponse
    {
        Gate::authorize('integrations.configure');
        $count = $sync->handle();
        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{0} No templates found.|{1} :count template synced.|[2,*] :count templates synced.', $count, ['count' => $count])]);

        return back();
    }

    public function pair(StartWahaSession $start): RedirectResponse
    {
        Gate::authorize('integrations.configure');
        $start->handle();

        return back();
    }

    /** Pairing state for the QR card (polled while pairing). */
    public function pairing(WahaDriver $driver, CheckIntegrationHealth $check): JsonResponse
    {
        Gate::authorize('integrations.configure');
        $connection = Connections::find(IntegrationType::WhatsAppWaha);
        abort_unless($connection instanceof IntegrationConnection, 404);

        try {
            $status = $driver->status($connection);
        } catch (Throwable) {
            return response()->json(['status' => 'UNREACHABLE', 'qr' => null]);
        }

        if ($status === 'WORKING' && $connection->status !== 'healthy') {
            $check->handle($connection);
        }

        return response()->json(['status' => $status, 'qr' => $status === 'SCAN_QR_CODE' ? $driver->qr($connection) : null]);
    }

    public function routing(Request $request, SaveWhatsAppRouting $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('integrations.configure');
        $request->validate([
            'chains' => ['required', 'array'],
            'chains.*' => ['array', 'max:3'],
            'chains.*.*' => ['string', Rule::in(WhatsAppRouting::STEPS)],
            'verificationTemplate' => ['nullable', 'string', 'max:120'],
        ]);

        /** @var array<string, list<string>> $chains */
        $chains = $request->input('chains');
        $save->handle(Actor::user($user), $chains, $request->filled('verificationTemplate') ? $request->string('verificationTemplate')->toString() : null);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Routing saved.')]);

        return back();
    }

    public function test(Request $request, SendWhatsAppTest $send, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('integrations.configure');
        $request->validate(['driver' => ['required', Rule::in(['whatsapp_official', 'whatsapp_waha'])], 'template' => ['nullable', 'string', 'max:120']]);

        $send->handle($user, IntegrationType::from($request->string('driver')->toString()), $request->filled('template') ? $request->string('template')->toString() : null);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test message sent.')]);

        return back();
    }

    public function invite(InviteToWhatsApp $invite, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('integrations.configure');
        $count = $invite->handle(Actor::user($user));
        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{0} Everyone has connected already.|{1} :count person invited.|[2,*] :count people invited.', $count, ['count' => $count])]);

        return back();
    }

    private function optional(Request $request, string $key): ?string
    {
        return $request->filled($key) ? $request->string($key)->toString() : null;
    }
}
