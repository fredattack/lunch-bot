<?php

namespace App\Actions\Lunch;

use App\Enums\LunchSessionStatus;
use App\Models\LunchSession;
use Carbon\Carbon;

class CreateLunchSession
{
    public function handle(string $date, string $channelId, Carbon $deadlineAt, string $provider = 'slack'): LunchSession
    {
        $session = LunchSession::firstOrCreate(
            ['date' => $date, 'provider_channel_id' => $channelId],
            [
                'provider' => $provider,
                'deadline_at' => $deadlineAt,
                'status' => LunchSessionStatus::Open,
            ]
        );

        if ($session->deadline_at->ne($deadlineAt)) {
            $session->deadline_at = $deadlineAt;
            $session->save();
        }

        return $session;
    }
}
