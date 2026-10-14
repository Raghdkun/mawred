<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Setup;

use App\Domain\Access\Models\User;
use App\Domain\Integrations\Actions\SaveEmailSettings;
use App\Domain\Integrations\Actions\SendTestEmail;
use App\Domain\Integrations\Data\EmailSettingsData;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Integrations\Support\RuntimeMailer;
use App\Domain\Organization\Actions\SaveBranch;
use App\Domain\Organization\Data\BranchData;
use App\Domain\Organization\Data\BranchInputData;
use App\Domain\Organization\Models\Branch;
use App\Domain\Policies\Actions\ExportPolicyPreset;
use App\Domain\Policies\Actions\InstallHolidayPreset;
use App\Domain\Policies\Models\HolidayCalendar;
use App\Domain\Policies\Models\PolicySet;
use App\Domain\Policies\Support\HolidayPresets;
use App\Domain\Policies\Support\PolicyPresets;
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
use Carbon\CarbonImmutable;
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
            'policies' => $this->policySummary(),
            'email' => $this->emailSummary(),
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

    /**
     * Step 5: the neutral default policies are already in force; HR reviews them here and can add a
     * ready-made holiday calendar for the first branch.
     */
    public function savePolicies(Request $request, #[CurrentUser] User $user, InstallHolidayPreset $install, CompleteSetupStep $complete, SetupState $state): RedirectResponse
    {
        Gate::authorize('setup.configure');
        $request->validate(['holidayPreset' => ['nullable', Rule::in(array_keys(HolidayPresets::all()))]]);

        if ($request->filled('holidayPreset')) {
            $branch = $this->firstBranch();
            $install->handle(Actor::user($user), $request->string('holidayPreset')->toString(), $branch instanceof Branch ? [$branch->ulid] : []);
        }

        $complete->handle(Actor::user($user), SetupStep::Policies);

        return to_route('setup.show', ['step' => $state->current()->value]);
    }

    /**
     * Step 7: the outgoing email connection (ADR-0015). Saving completes the step; a test send is
     * optional but its result shows on the step.
     */
    public function saveEmail(EmailSettingsData $data, #[CurrentUser] User $user, SaveEmailSettings $save, CompleteSetupStep $complete, SetupState $state): RedirectResponse
    {
        Gate::authorize('setup.configure');

        $save->handle(Actor::user($user), $data);
        $complete->handle(Actor::user($user), SetupStep::Email);

        return to_route('setup.show', ['step' => $state->current()->value]);
    }

    public function testEmail(#[CurrentUser] User $user, SendTestEmail $send): RedirectResponse
    {
        Gate::authorize('setup.configure');

        $send->handle($user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test email sent to :email.', ['email' => $user->email])]);

        return to_route('setup.show', ['step' => SetupStep::Email->value]);
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

    /**
     * @return array{values: array<string, mixed>, holidayPresets: list<array{key: string, name: array{en: string, ar: string}, installed: bool}>}
     */
    private function policySummary(): array
    {
        $neutral = PolicySet::query()->where('preset_key', PolicyPresets::NEUTRAL_KEY)->first();
        $values = $neutral instanceof PolicySet
            ? resolve(ExportPolicyPreset::class)->handle($neutral, CarbonImmutable::now('UTC')->toDateString())['values']
            : [];
        $installed = HolidayCalendar::query()->whereNotNull('preset_key')->pluck('preset_key')->all();

        return [
            'values' => array_intersect_key($values, array_flip(['leave.type.ANNUAL', 'overtime.rules', 'attendance.grace', 'employment.probation'])),
            'holidayPresets' => array_map(fn (string $key, array $preset): array => [
                'key' => $key,
                'name' => $preset['name'],
                'installed' => in_array($key, $installed, true),
            ], array_keys(HolidayPresets::all()), array_values(HolidayPresets::all())),
        ];
    }

    /**
     * The saved email connection without its password (ADR-0015: never echoed back).
     *
     * @return array<string, mixed>|null
     */
    private function emailSummary(): ?array
    {
        $connection = RuntimeMailer::connection();

        return $connection instanceof IntegrationConnection ? [
            'host' => $connection->config['host'] ?? '',
            'port' => $connection->config['port'] ?? 587,
            'security' => $connection->config['security'] ?? 'starttls',
            'username' => $connection->config['username'] ?? null,
            'fromAddress' => $connection->config['from_address'] ?? '',
            'fromName' => $connection->config['from_name'] ?? '',
            'hasPassword' => ($connection->credentials['password'] ?? '') !== '',
            'status' => $connection->status,
            'lastError' => $connection->last_error,
        ] : null;
    }

    private function firstBranch(): ?Branch
    {
        return Branch::query()->with('costCenter:id,ulid')->oldest('id')->first();
    }
}
