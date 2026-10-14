<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\People\Models\Employee;
use App\Domain\Setup\Support\Settings;
use App\Domain\Setup\Support\SetupState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    /**
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(
        private readonly ScopeResolver $access,
        private readonly Settings $settings,
        private readonly SetupState $setup,
    ) {}

    /**
     * Props every page receives. Only display data and public ids are shared; never models.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $user = $user instanceof User ? $user : null;
        $locale = App::getLocale();

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'locale' => $locale,
            'dir' => SetLocale::direction($locale),
            'locales' => SetLocale::SUPPORTED,
            'auth' => fn (): ?array => $user instanceof User ? $this->authPayload($user) : null,
            'impersonation' => fn (): ?array => $this->impersonationPayload($request, $user),
            'company' => $this->companyPayload(...),
            'setup' => fn (): array => ['complete' => $this->setup->isComplete()],
            'notifications' => fn (): ?array => $user instanceof User ? ['unread' => $user->unreadNotifications()->count()] : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function authPayload(User $user): array
    {
        $access = $this->access->for($user);
        // People with an employee record are greeted by their name in the current language.
        $employee = Employee::query()->where('user_id', $user->id)->first(['id', 'name_en', 'name_ar', 'preferred_name']);

        return [
            'user' => [
                'id' => $user->ulid,
                'name' => $user->name,
                'displayName' => $employee?->displayName() ?? $user->name,
                'email' => $user->email,
                'emailVerified' => $user->email_verified_at !== null,
                'twoFactorEnabled' => $user->two_factor_confirmed_at !== null,
                'preferences' => [
                    'locale' => $user->locale,
                    'digits' => $user->digits,
                    'showHijri' => $user->show_hijri,
                    'timezone' => $user->timezone,
                ],
            ],
            'access' => [
                'permissions' => $access->permissions(),
                'roles' => $access->roles,
                'isSuperAdmin' => $access->isSuperAdmin,
                'homeLayout' => $access->homeLayout->value,
                'requiresTwoFactor' => $access->requiresTwoFactor,
            ],
        ];
    }

    /**
     * @return array{impersonator: string, target: string}|null
     */
    private function impersonationPayload(Request $request, ?User $user): ?array
    {
        if (! $user instanceof User || ! $request->hasSession() || ! $request->session()->has(ApplyImpersonation::TARGET)) {
            return null;
        }

        $impersonatorId = $request->session()->get(ApplyImpersonation::IMPERSONATOR);
        $impersonator = is_int($impersonatorId) ? User::query()->find($impersonatorId) : null;

        return $impersonator instanceof User
            ? ['impersonator' => $impersonator->name, 'target' => $user->name]
            : null;
    }

    /**
     * @return array{name: array<string, string>|null, logoUrl: string|null}
     */
    private function companyPayload(): array
    {
        $name = $this->settings->get('company.name');
        $logo = $this->settings->get('company.logo_path');

        $names = null;
        if (is_array($name)) {
            $names = [];
            foreach ($name as $locale => $value) {
                if (is_string($value)) {
                    $names[(string) $locale] = $value;
                }
            }
        }

        return [
            'name' => $names,
            'logoUrl' => is_string($logo) ? Storage::disk('public')->url($logo) : null,
        ];
    }
}
