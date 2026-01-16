<?php

namespace App\Actions\Lunch;

use App\Enums\LunchDayStatus;
use App\Enums\ProposalStatus;
use App\Models\LunchDay;

class CloseLunchDay
{
    public function handle(LunchDay $day): LunchDay
    {
        $day->status = LunchDayStatus::Closed;
        $day->save();

        $day->proposals()->update(['status' => ProposalStatus::Closed]);

        return $day;
    }
}
