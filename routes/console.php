<?php

use App\Actions\LunchSession\CreateLunchSession;
use App\Actions\LunchSession\LockExpiredSessions;
use App\Actions\QuickRun\LockExpiredQuickRuns;
use App\Models\Organization;
use App\Services\Slack\SlackMessenger;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schedule;

// Schedule::call(function () {
//     $timezone = config('lunch.timezone', 'Europe/Paris');
//     $date = Carbon::now($timezone)->toDateString();
//     $deadlineTime = config('lunch.deadline_time', '11:30');
//     $deadlineAt = Carbon::parse("{$date} {$deadlineTime}", $timezone);
//
//     $createSession = app(CreateLunchSession::class);
//     $messenger = app(SlackMessenger::class);
//
//     Organization::with('installation')
//         ->whereHas('installation')
//         ->each(function (Organization $organization) use ($createSession, $messenger, $date, $deadlineAt) {
//             Organization::setCurrent($organization);
//
//             $channelId = $organization->installation->default_channel_id ?? config('lunch.channel_id');
//             if (! $channelId) {
//                 return;
//             }
//
//             $session = $createSession->handle($date, $channelId, $deadlineAt);
//             $messenger->postDailyKickoff($session);
//         });
//
//     Organization::setCurrent(null);
// })->dailyAt(config('lunch.post_time', '11:00'))->timezone(config('lunch.timezone', 'Europe/Paris'));

Schedule::call(function () {
    $lockAction = app(LockExpiredSessions::class);
    $messenger = app(SlackMessenger::class);

    Organization::with('installation')
        ->whereHas('installation')
        ->each(function (Organization $organization) use ($lockAction, $messenger) {
            Organization::setCurrent($organization);

            $lockedSessions = $lockAction->handle();

            if ($lockedSessions->isNotEmpty()) {
                $messenger->notifySessionsLocked($lockedSessions);
            }
        });

    Organization::setCurrent(null);
})->everyMinute();

Schedule::call(function () {
    $lockAction = app(LockExpiredQuickRuns::class);
    $messenger = app(SlackMessenger::class);

    Organization::with('installation')
        ->whereHas('installation')
        ->each(function (Organization $organization) use ($lockAction, $messenger) {
            Organization::setCurrent($organization);

            $lockedQuickRuns = $lockAction->handle();

            if ($lockedQuickRuns->isNotEmpty()) {
                $messenger->notifyQuickRunsLocked($lockedQuickRuns);
            }
        });

    Organization::setCurrent(null);
})->everyMinute();
