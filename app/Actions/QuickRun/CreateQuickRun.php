<?php

namespace App\Actions\QuickRun;

use App\Enums\QuickRunStatus;
use App\Models\QuickRun;
use Carbon\Carbon;

class CreateQuickRun
{
    /**
     * @param  array{destination: string, delay_minutes: int, note?: string|null}  $data
     */
    public function handle(string $userId, string $channelId, array $data): QuickRun
    {
        $deadlineAt = Carbon::now(config('lunch.timezone', 'Europe/Paris'))
            ->addMinutes($data['delay_minutes']);

        return QuickRun::create([
            'provider_user_id' => $userId,
            'destination' => $data['destination'],
            'deadline_at' => $deadlineAt,
            'status' => QuickRunStatus::Open,
            'note' => $data['note'] ?? null,
            'provider_channel_id' => $channelId,
        ]);
    }
}
