<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Notifications;

use App\Domain\Access\Models\User;
use App\Domain\Notifications\Actions\ResetNotificationTemplate;
use App\Domain\Notifications\Actions\SaveNotificationTemplate;
use App\Domain\Notifications\Models\NotificationTemplate;
use App\Domain\Notifications\Support\NotificationCatalog;
use App\Domain\Notifications\Support\TemplateRenderer;
use App\Http\Controllers\Controller;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Notification templates editor (handoff 42): every event with its in-app and email wording
 * in English and Arabic, variable chips and a live preview. WhatsApp joins in Phase 4.
 */
final class NotificationTemplatesController extends Controller
{
    public function index(TemplateRenderer $renderer, Request $request): Response
    {
        Gate::authorize('notifications.configure');

        $request->validate(['event' => ['nullable', 'string', Rule::in(array_keys(NotificationCatalog::events()))]]);
        $events = NotificationCatalog::events();
        $selected = $request->string('event')->toString() ?: 'approvals.task_assigned';
        $overrides = NotificationTemplate::query()->get(['event', 'channel'])->map(fn (NotificationTemplate $t): string => "{$t->event}:{$t->channel}")->unique()->all();

        return Inertia::render('notifications/templates', [
            'events' => array_map(fn (string $event, array $definition): array => [
                'key' => $event,
                'category' => $definition['category']->value,
                'customised' => array_values(array_filter(array_keys($definition['templates']), fn (string $channel): bool => in_array("{$event}:{$channel}", $overrides, true))),
            ], array_keys($events), $events),
            'selected' => $selected,
            'variables' => $events[$selected]['variables'],
            'channels' => collect($events[$selected]['templates'])->map(fn (array $defaults, string $channel): array => [
                'channel' => $channel,
                'customised' => in_array("{$selected}:{$channel}", $overrides, true),
                'locales' => collect(['en', 'ar'])->mapWithKeys(fn (string $locale): array => [$locale => [
                    'subject' => $renderer->template($selected, $channel, $locale)['subject'] ?? '',
                    'body' => $renderer->template($selected, $channel, $locale)['body'],
                ]])->all(),
            ])->values()->all(),
            'samples' => $this->samples(),
        ]);
    }

    public function update(Request $request, string $event, string $channel, SaveNotificationTemplate $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('notifications.configure');
        $request->validate([
            'locales' => ['required', 'array:en,ar'],
            'locales.*.subject' => ['nullable', 'string', 'max:255'],
            'locales.*.body' => ['required', 'string', 'max:5000'],
        ]);

        /** @var array<string, array{subject?: string|null, body: string}> $locales */
        $locales = $request->input('locales');
        $save->handle($user, $event, $channel, $locales);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Template saved.')]);

        return back();
    }

    public function destroy(string $event, string $channel, ResetNotificationTemplate $reset, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('notifications.configure');
        abort_unless(isset(NotificationCatalog::events()[$event]['templates'][$channel]), 404);

        $reset->handle($user, $event, $channel);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Back to the default wording.')]);

        return back();
    }

    /**
     * Example values for the preview, per locale.
     *
     * @return array<string, array<string, string>>
     */
    private function samples(): array
    {
        return [
            'en' => ['name' => 'Sara', 'requester' => 'Omar Haddad', 'request_type' => 'Business trip', 'request_number' => 'REQ-000123', 'link' => 'https://mawred.example/requests/…', 'step' => 'Manager approval', 'due_at' => '2026-10-16 17:00', 'comment' => 'Please attach the agenda.'],
            'ar' => ['name' => 'سارة', 'requester' => 'عمر حداد', 'request_type' => 'رحلة عمل', 'request_number' => 'REQ-000123', 'link' => 'https://mawred.example/requests/…', 'step' => 'موافقة المدير', 'due_at' => '2026-10-16 17:00', 'comment' => 'يرجى إرفاق جدول الأعمال.'],
        ];
    }
}
