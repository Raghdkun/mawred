<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Setup;

use App\Domain\Access\Models\User;
use App\Domain\Organization\Actions\SaveBranch;
use App\Domain\Organization\Data\BranchData;
use App\Domain\Organization\Data\BranchInputData;
use App\Domain\Organization\Models\Branch;
use App\Domain\Setup\Actions\CompleteSetupStep;
use App\Domain\Setup\Actions\FinishSetup;
use App\Domain\Setup\Actions\SaveCompanyProfile;
use App\Domain\Setup\Actions\SaveLanguageSettings;
use App\Domain\Setup\Actions\SkipSetupStep;
use App\Domain\Setup\Enums\SetupStep;
use App\Domain\Setup\Enums\StepStatus;
use App\Domain\Setup\Support\Settings;
use App\Domain\Setup\Support\SetupState;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Setup wizard frame (handoff screen 2). Individual step forms land in the phase that owns
 * their data (branch: P1, policies: P2, email: P3, WhatsApp/Cognito: P4).
 */
final class SetupWizardController extends Controller
{
    public function show(SetupState $state, Settings $settings, #[CurrentUser] User $user, CompleteSetupStep $complete, ?string $step = null): Response
    {
        Gate::authorize('setup.configure');

        // The admin step is satisfied once the owner has confirmed 2FA (enforced by middleware).
        if ($user->two_factor_confirmed_at !== null && $state->status(SetupStep::Admin) === StepStatus::Pending) {
            $complete->handle(Actor::user($user), SetupStep::Admin);
        }

        $current = SetupStep::tryFrom((string) $step) ?? $state->current();
        $firstBranch = $this->firstBranch();

        return Inertia::render('setup/wizard', [
            'current' => $current->value,
            'company' => [
                'name' => $settings->get('company.name', ['en' => '', 'ar' => '']),
                'legalName' => $settings->get('company.legal_name'),
                'accent' => $settings->get('company.accent'),
            ],
            'languages' => [
                'defaultLocale' => $settings->get('locales.default', 'en'),
                'defaultDigits' => $settings->get('locales.default_digits', 'latn'),
                'hijriByDefault' => (bool) $settings->get('locales.hijri_default', false),
            ],
            'branch' => $firstBranch instanceof Branch ? BranchData::fromBranch($firstBranch) : null,
            'admin' => [
                'name' => $user->name,
                'email' => $user->email,
                'twoFactorEnabled' => $user->two_factor_confirmed_at !== null,
            ],
            'complete' => $state->isComplete(),
            'steps' => array_map(fn (SetupStep $s): array => [
                'key' => $s->value,
                'number' => $s->number(),
                'status' => $state->status($s)->value,
                'required' => $s->isRequired(),
                'availableInPhase' => $s->availableInPhase(),
            ], SetupStep::cases()),
        ]);
    }

    public function saveCompany(Request $request, #[CurrentUser] User $user, SaveCompanyProfile $save, CompleteSetupStep $complete, SetupState $state): RedirectResponse
    {
        Gate::authorize('setup.configure');

        $request->validate([
            'name.en' => ['required', 'string', 'max:120'],
            'name.ar' => ['required', 'string', 'max:120'],
            'legalName' => ['nullable', 'string', 'max:200'],
            'accent' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
            'removeLogo' => ['sometimes', 'boolean'],
        ]);

        $save->handle(
            Actor::user($user),
            ['en' => $request->string('name.en')->trim()->toString(), 'ar' => $request->string('name.ar')->trim()->toString()],
            $request->filled('legalName') ? $request->string('legalName')->trim()->toString() : null,
            $request->filled('accent') ? strtoupper($request->string('accent')->toString()) : null,
            $request->file('logo') instanceof UploadedFile ? $request->file('logo') : null,
            $request->boolean('removeLogo'),
        );
        $complete->handle(Actor::user($user), SetupStep::Company);

        return to_route('setup.show', ['step' => $state->current()->value]);
    }

    public function saveLanguages(Request $request, #[CurrentUser] User $user, SaveLanguageSettings $save, CompleteSetupStep $complete, SetupState $state): RedirectResponse
    {
        Gate::authorize('setup.configure');

        $request->validate([
            'defaultLocale' => ['required', Rule::in(SetLocale::SUPPORTED)],
            'defaultDigits' => ['required', Rule::in(['latn', 'arab'])],
            'hijriByDefault' => ['required', 'boolean'],
        ]);

        $save->handle(Actor::user($user), $request->string('defaultLocale')->toString(), $request->string('defaultDigits')->toString(), $request->boolean('hijriByDefault'));
        $complete->handle(Actor::user($user), SetupStep::Languages);

        return to_route('setup.show', ['step' => $state->current()->value]);
    }

    /**
     * Step 4: the first branch. Saving again edits that branch rather than adding another.
     */
    public function saveBranch(BranchInputData $data, #[CurrentUser] User $user, SaveBranch $save, CompleteSetupStep $complete, SetupState $state): RedirectResponse
    {
        Gate::authorize('setup.configure');
        Gate::authorize('organization.create');

        $save->handle($data, $this->firstBranch());
        $complete->handle(Actor::user($user), SetupStep::Branch);

        return to_route('setup.show', ['step' => $state->current()->value]);
    }

    public function complete(SetupStep $step, #[CurrentUser] User $user, CompleteSetupStep $complete, SetupState $state): RedirectResponse
    {
        Gate::authorize('setup.configure');

        $complete->handle(Actor::user($user), $step);

        return to_route('setup.show', ['step' => $state->current()->value]);
    }

    public function skip(SetupStep $step, #[CurrentUser] User $user, SkipSetupStep $skip, SetupState $state): RedirectResponse
    {
        Gate::authorize('setup.configure');

        $skip->handle(Actor::user($user), $step);

        return to_route('setup.show', ['step' => $state->current()->value]);
    }

    public function finish(#[CurrentUser] User $user, FinishSetup $finish): RedirectResponse
    {
        Gate::authorize('setup.configure');

        $finish->handle(Actor::user($user));

        return to_route('setup.show', ['step' => SetupStep::Review->value]);
    }

    private function firstBranch(): ?Branch
    {
        return Branch::query()->with('costCenter:id,ulid')->oldest('id')->first();
    }
}
