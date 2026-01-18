<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// TODO: Implement scheduled tasks when LunchManager is created
// Schedule::call(function (LunchManager $manager) {
//     $day = $manager->ensureTodayLunchDay();
//     if ($day) {
//         $manager->postDailyKickoff($day);
//     }
// })->dailyAt(config('lunch.post_time'))->timezone(config('lunch.timezone'));
//
// Schedule::call(function (LunchManager $manager) {
//     $manager->lockExpiredDays();
// })->everyMinute();
