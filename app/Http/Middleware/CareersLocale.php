<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Careers pages take `?lang=ar|en` (D87), so each language has its own address for search
 * engines and shared links; the choice sticks for the rest of the visit.
 */
final class CareersLocale
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $lang = $request->query('lang');

        if (is_string($lang) && in_array($lang, SetLocale::SUPPORTED, true)) {
            App::setLocale($lang);
            CarbonImmutable::setLocale($lang);

            if ($request->hasSession()) {
                $request->session()->put(SetLocale::SESSION_KEY, $lang);
            }
        }

        return $next($request);
    }
}
