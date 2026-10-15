<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Schedule;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/** My schedule (handoff 23). */
final class MyScheduleController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('schedule/mine');
    }
}
