<?php

namespace App\Actions\Lunch;

use App\Enums\LunchSessionStatus;
use App\Models\LunchSession;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LockExpiredSessions
{
    /**
     * @return Collection<int, LunchSession>
     */
    public function handle(?string $timezone = null): Collection
    {
        $timezone = $timezone ?? config('lunch.timezone', 'UTC');
        $now = Carbon::now($timezone);

        $sessions = LunchSession::query()
            ->where('status', LunchSessionStatus::Open)
            ->where('deadline_at', '<=', $now)
            ->get();

        foreach ($sessions as $session) {
            $session->status = LunchSessionStatus::Locked;
            $session->save();
        }

        return $sessions;
    }
}
