<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('lesson-authoring:cleanup')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('notifications:send-deadline-reminders')
    ->hourly()
    ->withoutOverlapping();

// Availability is time-sensitive (a Student should see "available" shortly
// after opens_at passes), so this runs more often than the deadline reminder.
Schedule::command('notifications:send-assessment-availability')
    ->everyFiveMinutes()
    ->withoutOverlapping();
