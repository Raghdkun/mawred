<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Domain\Access\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use DateTimeZone;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Language, digits, Hijri display, timezone and appearance (brief §17, ADR-0004/0011).
 */
final class PreferencesController extends Controller
{
    public function edit(#[CurrentUser] User $user): Response
    {
        return Inertia::render('settings/preferences', [
            'preferences' => [
                'locale' => $user->locale,
                'digits' => $user->digits,
                'showHijri' => $user->show_hijri,
                'timezone' => $user->timezone,
            ],
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(Request $request, #[CurrentUser] User $user): RedirectResponse
    {
        $request->validate([
            'locale' => ['required', Rule::in(SetLocale::SUPPORTED)],
            'digits' => ['required', Rule::in(['latn', 'arab'])],
            'showHijri' => ['required', 'boolean'],
            'timezone' => ['nullable', 'timezone:all'],
        ]);

        $user->forceFill([
            'locale' => $request->string('locale')->toString(),
            'digits' => $request->string('digits')->toString(),
            'show_hijri' => $request->boolean('showHijri'),
            'timezone' => $request->filled('timezone') ? $request->string('timezone')->toString() : null,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Preferences saved.')]);

        return back();
    }
}
