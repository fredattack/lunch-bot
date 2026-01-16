<?php

namespace App\Actions\Lunch;

use App\Enums\LunchDayStatus;
use App\Models\LunchDay;
use Carbon\Carbon;

class CreateLunchDay
{
    public function handle(string $date, string $channelId, Carbon $deadlineAt, string $provider = 'slack'): LunchDay
    {
        $day = LunchDay::firstOrCreate(
            ['date' => $date, 'provider_channel_id' => $channelId],
            [
                'provider' => $provider,
                'deadline_at' => $deadlineAt,
                'status' => LunchDayStatus::Open,
            ]
        );

        if ($day->deadline_at->ne($deadlineAt)) {
            $day->deadline_at = $deadlineAt;
            $day->save();
        }

        return $day;
    }
}
