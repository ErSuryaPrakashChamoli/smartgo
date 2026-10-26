<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('content:publish-scheduled')->everyMinute()->withoutOverlapping(10);
Schedule::command('media:licence-reminders')->dailyAt('09:00')->timezone(config('backoffice.timezone'))->withoutOverlapping(30);
