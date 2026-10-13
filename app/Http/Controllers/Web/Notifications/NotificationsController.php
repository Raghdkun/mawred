<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Notifications;

use App\Domain\Access\Models\User;
use App\Domain\Notifications\Data\NotificationItemData;
use App\Http\Controllers\Controller;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Notification center (handoff screen 44). Preferences per category × channel land in Phase 3
 * together with the notification categories.
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
}
