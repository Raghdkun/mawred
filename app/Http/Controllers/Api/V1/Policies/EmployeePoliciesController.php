<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Policies;

use App\Domain\People\Models\Employee;
use App\Domain\Policies\Data\PolicyKeyData;
use App\Domain\Policies\Support\PolicyKeys;
use App\Domain\Policies\Support\PolicyResolver;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Every policy value in force for an employee on a date, each with its precedence chain.
 */
final class EmployeePoliciesController extends Controller
{
    public function index(Request $request, Employee $employee, PolicyResolver $resolver, PolicyKeys $keys): JsonResponse
    {
        // Out-of-scope records answer 404, so the API never confirms they exist.
        abort_unless(Gate::allows('view', $employee), 404);
        Gate::authorize('policies.view');
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        $date = $request->string('date', CarbonImmutable::now('UTC')->toDateString())->toString();

        return response()->json([
            'data' => array_values($resolver->all($employee, $date)),
            'meta' => ['date' => $date, 'keys' => array_values(array_map(PolicyKeyData::fromKey(...), $keys->all()))],
        ]);
    }
}
