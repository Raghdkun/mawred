<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Notifications;

use App\Domain\Access\Models\User;
use App\Domain\Integrations\Actions\ConfirmWhatsAppNumber;
use App\Domain\Integrations\Actions\RemoveWhatsAppNumber;
use App\Domain\Integrations\Actions\StartWhatsAppVerification;
use App\Domain\Integrations\Enums\IntegrationType;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Integrations\Models\WhatsAppContact;
use App\Domain\Integrations\WhatsApp\WhatsAppGateway;
use App\Domain\Notifications\Actions\RemovePushSubscription;
use App\Domain\Notifications\Actions\SaveNotificationPreferences;
use App\Domain\Notifications\Actions\SavePushSubscription;
use App\Domain\Notifications\Data\NotificationItemData;
use App\Domain\Notifications\Data\NotificationPreferencesData;
use App\Domain\Notifications\Models\PushSubscription;
use App\Domain\Notifications\Support\NotificationPreferences;
use App\Domain\People\Models\Employee;
use App\Http\Controllers\Controller;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Notification center and preferences (handoff screen 44).
 */
final class NotificationsController extends Controller
{
    public function index(Request $request, #[CurrentUser] User $user): Response
    {
        $filter = $request->string('filter')->toString() === 'unread' ? 'unread' : 'all';

        $page = $user->notifications()
            ->when($filter === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->latest()
            ->cursorPaginate(30)
            ->withQueryString();

        return Inertia::render('notifications/index', [
            'notifications' => [
                'data' => array_map(NotificationItemData::fromNotification(...), $page->items()),
                'nextCursor' => $page->nextCursor()?->encode(),
            ],
            'filter' => $filter,
        ]);
    }

    public function markRead(DatabaseNotification $notification, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless($notification->notifiable_type === $user->getMorphClass() && $notification->notifiable_id === $user->id, 404);

        $notification->markAsRead();

        return back();
    }

    public function markAllRead(#[CurrentUser] User $user): RedirectResponse
    {
        $user->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    public function preferences(NotificationPreferences $preferences, #[CurrentUser] User $user): Response
    {
        return Inertia::render('notifications/preferences', [
            'matrix' => $preferences->matrix($user),
            'quietHours' => ['start' => $this->time($user->quiet_hours_start), 'end' => $this->time($user->quiet_hours_end)],
            'digest' => $user->digest_enabled,
            'timezone' => $preferences->timezone($user),
            'whatsapp' => $this->whatsApp($user),
            'push' => [
                'publicKey' => is_string(config('services.webpush.public_key')) && config('services.webpush.public_key') !== '' ? config('services.webpush.public_key') : null,
                'devices' => PushSubscription::query()->where('user_id', $user->id)->count(),
            ],
        ]);
    }

    /** Push on for this browser or phone (D128), called by the page after the browser subscribes. */
    public function subscribePush(Request $request, SavePushSubscription $save, #[CurrentUser] User $user): HttpResponse
    {
        $request->validate([
            'endpoint' => ['required', 'string', 'url:https', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:200'],
            'keys.auth' => ['required', 'string', 'max:100'],
            'contentEncoding' => ['nullable', 'in:aes128gcm,aesgcm'],
        ]);
        $save->handle($user, $request->string('endpoint')->toString(), $request->string('keys.p256dh')->toString(), $request->string('keys.auth')->toString(), $request->string('contentEncoding', 'aes128gcm')->toString(), $request->userAgent());

        return response()->noContent();
    }

    public function unsubscribePush(Request $request, RemovePushSubscription $remove, #[CurrentUser] User $user): HttpResponse
    {
        $request->validate(['endpoint' => ['required', 'string', 'max:2000']]);
        $remove->handle($user, $request->string('endpoint')->toString());

        return response()->noContent();
    }

    public function startWhatsApp(Request $request, StartWhatsAppVerification $start, #[CurrentUser] User $user): RedirectResponse
    {
        $request->validate(['phone' => ['required', 'string', 'max:30']]);
        $start->handle($user, $request->string('phone')->toString());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('We sent a code to your WhatsApp.')]);

        return back();
    }

    public function confirmWhatsApp(Request $request, ConfirmWhatsAppNumber $confirm, #[CurrentUser] User $user): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:10']]);
        $confirm->handle($user, $request->string('code')->toString());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('WhatsApp connected.')]);

        return back();
    }

    public function removeWhatsApp(RemoveWhatsAppNumber $remove, #[CurrentUser] User $user): RedirectResponse
    {
        $remove->handle($user);

        return back();
    }

    /**
     * @return array{available: bool, phone: string|null, verified: bool, pending: bool, suggested: string|null}
     */
    private function whatsApp(User $user): array
    {
        $contact = WhatsAppContact::query()->where('user_id', $user->id)->first();

        return [
            'available' => WhatsAppGateway::usable(IntegrationType::WhatsAppOfficial) instanceof IntegrationConnection || WhatsAppGateway::usable(IntegrationType::WhatsAppWaha) instanceof IntegrationConnection,
            'phone' => $contact?->phone_e164,
            'verified' => $contact?->verified_at !== null,
            'pending' => $contact?->verified_at === null && $contact?->code_expires_at?->isFuture() === true,
            'suggested' => $contact instanceof WhatsAppContact ? null : $this->suggestedPhone($user),
        ];
    }

    private function suggestedPhone(User $user): ?string
    {
        $work = Employee::query()->where('user_id', $user->id)->value('work_phone_e164');

        return is_string($work) ? $work : $user->phone_e164;
    }

    public function updatePreferences(NotificationPreferencesData $data, SaveNotificationPreferences $save, #[CurrentUser] User $user): RedirectResponse
    {
        $save->handle($user, $data);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notification settings saved.')]);

        return back();
    }

    private function time(?string $value): ?string
    {
        return $value === null ? null : substr($value, 0, 5);
    }
}
