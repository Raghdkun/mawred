<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Access\Models\User;
use App\Domain\Setup\Support\Settings;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the request locale (ADR-0011, D3): the user's preference, then the guest's session
 * choice, then the browser's Accept-Language, then the company default (EN).
 */
final readonly class SetLocale
{
    public const array SUPPORTED = ['en', 'ar'];

    public const string SESSION_KEY = 'locale';

    public function __construct(private Settings $settings) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);

        App::setLocale($locale);
        CarbonImmutable::setLocale($locale);

        return $next($request);
    }

    public static function direction(string $locale): string
    {
        return $locale === 'ar' ? 'rtl' : 'ltr';
    }

    private function resolve(Request $request): string
    {
        $user = $request->user();

        if ($user instanceof User && in_array($user->locale, self::SUPPORTED, true)) {
            return $user->locale;
        }

        $session = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;

        if (is_string($session) && in_array($session, self::SUPPORTED, true)) {
            return $session;
        }

        $preferred = $request->getPreferredLanguage(self::SUPPORTED);

        if ($request->headers->has('Accept-Language') && is_string($preferred)) {
            return $preferred;
        }

        $default = $this->settings->get('locales.default', config('app.locale'));

        return is_string($default) && in_array($default, self::SUPPORTED, true) ? $default : 'en';
    }
}
