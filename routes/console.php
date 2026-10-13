<?php

declare(strict_types=1);

use App\Support\Http\IdempotencyKey;
use Illuminate\Support\Facades\Schedule;

Schedule::command('partitions:maintain')->dailyAt('01:15')->onOneServer()->withoutOverlapping();
Schedule::command('people:sync-placements')->hourlyAt(5)->onOneServer()->withoutOverlapping();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('model:prune', ['--model' => [IdempotencyKey::class]])->hourly();
