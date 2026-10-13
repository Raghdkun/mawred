<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Account;

use App\Domain\Access\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Language switch without a full reload (brief §17): the client changes i18next and `dir`,
 * this persists the choice (on the user, or in the session for guests).
 */
final class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $request->validate(['locale' => ['required', Rule::in(SetLocale::SUPPORTED)]]);
        $locale = $request->string('locale')->toString();
        $user = $request->user();

        if ($user instanceof User) {
            $user->forceFill(['locale' => $locale])->save();
        } else {
            $request->session()->put(SetLocale::SESSION_KEY, $locale);
        }

        return back();
    }
}
