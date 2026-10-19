<?php

declare(strict_types=1);

use App\Domain\Forms\Models\FormUpload;
use App\Support\Http\IdempotencyKey;
use Illuminate\Support\Facades\Schedule;

Schedule::command('partitions:maintain')->dailyAt('01:15')->onOneServer()->withoutOverlapping();
Schedule::command('people:sync-placements')->hourlyAt(5)->onOneServer()->withoutOverlapping();
Schedule::command('people:document-reminders')->dailyAt('06:00')->onOneServer()->withoutOverlapping();
Schedule::command('workflows:tick')->everyMinute()->onOneServer()->withoutOverlapping();
Schedule::command('integrations:health')->everyFiveMinutes()->onOneServer()->withoutOverlapping();
Schedule::command('attendance:close')->hourlyAt(20)->onOneServer()->withoutOverlapping();
Schedule::command('leave:accrue')->dailyAt('02:30')->onOneServer()->withoutOverlapping();
// Candidate retention (D86, D96): after the overnight jobs, before people start work.
Schedule::command('hiring:purge')->dailyAt('03:10')->onOneServer()->withoutOverlapping();
// Mandatory courses (D100): catch new hires and moves the events missed.
Schedule::command('training:assign')->dailyAt('02:40')->onOneServer()->withoutOverlapping();
// Certificate expiry (D103): reminders and renewal enrolments, before people start work.
Schedule::command('training:certificates')->dailyAt('06:10')->onOneServer()->withoutOverlapping();
// Report exports (D114): files are kept for a week.
Schedule::command('reports:prune')->dailyAt('03:40')->onOneServer()->withoutOverlapping();
Schedule::command('notifications:digest')->dailyAt('05:00')->onOneServer()->withoutOverlapping();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('model:prune', ['--model' => [IdempotencyKey::class, FormUpload::class]])->hourly();
