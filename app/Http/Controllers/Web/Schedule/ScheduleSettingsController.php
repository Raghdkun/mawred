<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedule;

use App\Domain\Organization\Models\Branch;
use App\Domain\Scheduling\Actions\DeleteShiftTemplate;
use App\Domain\Scheduling\Actions\SaveRotationPattern;
use App\Domain\Scheduling\Actions\SaveShiftTemplate;
use App\Domain\Scheduling\Data\ShiftTemplateData;
use App\Domain\Scheduling\Data\ShiftTemplateInputData;
use App\Domain\Scheduling\Models\RotationPattern;
use App\Domain\Scheduling\Models\ShiftTemplate;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Shift templates and rotation patterns (scheduling.configure). */
final class ScheduleSettingsController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('scheduling.configure');
        $templates = ShiftTemplate::query()->with('branch')->oldest('starts_at')->get();
        $ulids = [];

        foreach ($templates as $template) {
            $ulids[$template->id] = $template->ulid;
        }

        return Inertia::render('schedule/settings', [
            'templates' => array_values($templates->map(ShiftTemplateData::fromModel(...))->all()),
            'rotations' => array_values(RotationPattern::query()->orderBy('id')->get()->map(fn (RotationPattern $p): array => [
                'id' => $p->ulid,
                'name' => $p->getTranslations('name'),
                'days' => array_map(fn (?int $id): ?string => $id === null ? null : $ulids[$id] ?? null, $p->days),
            ])->all()),
        ]);
    }

    public function storeTemplate(ShiftTemplateInputData $input, SaveShiftTemplate $save): RedirectResponse
    {
        Gate::authorize('scheduling.configure');
        $save->handle($input);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Shift saved.')]);

        return back();
    }

    public function updateTemplate(ShiftTemplate $template, ShiftTemplateInputData $input, SaveShiftTemplate $save): RedirectResponse
    {
        Gate::authorize('scheduling.configure');
        $save->handle($input, $template);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Shift saved.')]);

        return back();
    }

    public function destroyTemplate(ShiftTemplate $template, DeleteShiftTemplate $delete): RedirectResponse
    {
        Gate::authorize('scheduling.configure');
        $delete->handle($template);

        return back();
    }

    public function saveRotation(Request $request, SaveRotationPattern $save, ?RotationPattern $rotation = null): RedirectResponse
    {
        Gate::authorize('scheduling.configure');
        $request->validate([
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:80'],
            'name.ar' => ['nullable', 'string', 'max:80'],
            'days' => ['required', 'array', 'min:1', 'max:56'],
            'days.*' => ['nullable', 'string', 'size:26', 'exists:shift_templates,ulid'],
            'branchId' => ['nullable', 'string', 'size:26', 'exists:branches,ulid'],
        ]);
        $days = [];

        foreach ($request->array('days') as $day) {
            $days[] = is_string($day) ? $day : null;
        }

        $branch = $request->filled('branchId') ? Branch::query()->where('ulid', $request->string('branchId')->toString())->first() : null;
        $save->handle(['en' => $request->string('name.en')->toString(), 'ar' => $request->string('name.ar')->toString()], $days, $branch, $rotation);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rotation saved.')]);

        return back();
    }
}
