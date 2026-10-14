<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Policies;

use App\Domain\People\Models\Employee;
use App\Domain\Policies\Data\PolicyKeyData;
use App\Domain\Policies\Support\PolicyKeys;
use App\Domain\Policies\Support\PolicyResolver;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Effective policy for one employee on a date, with the explainer chain (screen 19).
 */
final class EffectivePolicyController extends Controller
{
    public function show(Request $request, PolicyResolver $resolver, PolicyKeys $keys): Response
    {
        Gate::authorize('policies.view');
        $request->validate([
            'employee' => ['nullable', 'string', 'max:26'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $date = $request->string('date', CarbonImmutable::now('UTC')->toDateString())->toString();
        $employee = $request->filled('employee')
            ? Employee::query()->where('ulid', $request->string('employee')->toString())->first()
            : null;

        // Someone outside the viewer's people scope is treated as not found.
        if ($employee instanceof Employee && ! Gate::allows('view', $employee)) {
            $employee = null;
        }

        $locale = app()->getLocale();

        return Inertia::render('policies/effective', [
            'date' => $date,
            'employee' => $employee instanceof Employee ? [
                'id' => $employee->ulid,
                'label' => $employee->displayName($locale),
                'description' => $employee->employee_number,
            ] : null,
            'keys' => array_values(array_map(PolicyKeyData::fromKey(...), $keys->all())),
            'values' => $employee instanceof Employee ? array_values($resolver->all($employee, $date)) : [],
        ]);
    }
}
