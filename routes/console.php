<?php

declare(strict_types=1);

use App\Domain\Forms\Models\FormUpload;
use App\Support\Http\IdempotencyKey;
use Illuminate\Support\Facades\Schedule;

Schedule::command('partitions:maintain')->dailyAt('01:15')->onOneServer()->withoutOverlapping();
Schedule::command('people:sync-placements')->hourlyAt(5)->onOneServer()->withoutOverlapping();
Schedule::command('people:document-reminders')->dailyAt('06:00')->onOneServer()->withoutOverlapping();
Schedule::command('workflows:tick')->everyMinute()->onOneServer()->withoutOverlapping();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('model:prune', ['--model' => [IdempotencyKey::class, FormUpload::class]])->hourly();
