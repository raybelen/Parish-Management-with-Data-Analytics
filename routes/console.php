<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('office:reminders')->dailyAt('08:00')->timezone('Asia/Manila')->withoutOverlapping();
if (config('office.analytics_enabled')) {
    Schedule::command('analytics:forecast --all')->monthlyOn(1, '03:00')->timezone('Asia/Manila')->withoutOverlapping(30);
}

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
