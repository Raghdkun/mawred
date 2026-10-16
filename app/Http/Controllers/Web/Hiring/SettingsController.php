<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Hiring;

use App\Domain\Access\Models\User;
use App\Domain\Hiring\Actions\SaveHiringSettings;
use App\Domain\Hiring\Actions\SavePipelineStages;
use App\Domain\Hiring\Data\StageData;
use App\Domain\Hiring\Models\PipelineStage;
use App\Domain\Hiring\Support\HiringSettings;
use App\Http\Controllers\Controller;
use App\Support\Actors\Actor;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Hiring settings (`hiring.configure`): the default pipeline and the careers page texts. */
final class SettingsController extends Controller
{
    public function show(HiringSettings $settings): Response
    {
        Gate::authorize('hiring.configure');
        $pair = fn (array $text): array => ['en' => $text['en'] ?? '', 'ar' => $text['ar'] ?? ''];

        return Inertia::render('hiring/settings', [
            'stages' => array_values(PipelineStage::query()->whereNull('job_requisition_id')->orderBy('position')->get()->map(StageData::fromStage(...))->all()),
            'careersIntro' => $pair($settings->careersIntro()),
            'privacyNotice' => $pair($settings->privacyNotice()),
            'careersUrl' => route('careers.index'),
            'defaultFormId' => $settings->defaultForm()?->ulid,
        ]);
    }

    public function update(Request $request, SaveHiringSettings $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('hiring.configure');
        $request->validate([
            'careersIntro.en' => ['required', 'string', 'max:600'],
            'careersIntro.ar' => ['nullable', 'string', 'max:600'],
            'privacyNotice.en' => ['required', 'string', 'max:2000'],
            'privacyNotice.ar' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var array{en: string, ar?: string|null} $intro */
        $intro = $request->input('careersIntro');
        /** @var array{en: string, ar?: string|null} $notice */
        $notice = $request->input('privacyNotice');
        $save->handle(Actor::user($user), $intro, $notice);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Settings saved.')]);

        return back();
    }

    public function pipeline(Request $request, SavePipelineStages $save, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('hiring.configure');
        $save->handle(Actor::user($user), null, BoardController::validatedStages($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stages saved.')]);

        return back();
    }
}
