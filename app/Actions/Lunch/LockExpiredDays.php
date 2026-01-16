<?php

namespace App\Actions\Lunch;

use App\Enums\LunchDayStatus;
use App\Models\LunchDay;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LockExpiredDays
{
    /**
     * @return Collection<int, LunchDay>
     */
    public function handle(?string $timezone = null): Collection
    {
        $timezone = $timezone ?? config('lunch.timezone', 'UTC');
        $now = Carbon::now($timezone);

        $days = LunchDay::query()
            ->where('status', LunchDayStatus::Open)
            ->where('deadline_at', '<=', $now)
            ->get();

        foreach ($days as $day) {
            $day->status = LunchDayStatus::Locked;
            $day->save();
        }

        return $days;
    }
}
