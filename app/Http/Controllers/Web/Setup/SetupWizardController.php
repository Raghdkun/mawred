<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Setup;

use App\Domain\Access\Models\User;
use App\Domain\Setup\Actions\CompleteSetupStep;
use App\Domain\Setup\Actions\FinishSetup;
use App\Domain\Setup\Actions\SkipSetupStep;
use App\Domain\Setup\Enums\SetupStep;
use App\Domain\Setup\Enums\StepStatus;
use App\Domain\Setup\Support\SetupState;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Setup wizard frame (handoff screen 2). Individual step forms land in the phase that owns
 * their data (branch: P1, policies: P2, email: P3, WhatsApp/Cognito: P4).
 */
final class SetupWizardController extends Controller
{
    public function show(SetupState $state, #[CurrentUser] User $user, CompleteSetupStep $complete, ?string $step = null): Response
    {
        Gate::authorize('setup.configure');

        // The admin step is satisfied once the owner has confirmed 2FA (enforced by middleware).
        if ($user->two_factor_confirmed_at !== null && $state->status(SetupStep::Admin) === StepStatus::Pending) {
            $complete->handle(Actor::user($user), SetupStep::Admin);
        }

        $current = SetupStep::tryFrom((string) $step) ?? $state->current();

        return Inertia::render('setup/wizard', [
            'current' => $current->value,
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

        return to_route('home');
    }
}
