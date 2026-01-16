<?php

use App\Services\LunchManager;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (LunchManager $manager) {
    $day = $manager->ensureTodayLunchDay();
    if ($day) {
        $manager->postDailyKickoff($day);
    }
})->dailyAt(config('lunch.post_time'))->timezone(config('lunch.timezone'));

Schedule::call(function (LunchManager $manager) {
    $manager->lockExpiredDays();
})->everyMinute();
