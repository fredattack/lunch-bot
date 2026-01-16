<?php

namespace App\Actions\Lunch;

use App\Enums\LunchSessionStatus;
use App\Enums\ProposalStatus;
use App\Models\LunchSession;

class CloseLunchSession
{
    public function handle(LunchSession $session): LunchSession
    {
        $session->status = LunchSessionStatus::Closed;
        $session->save();

        $session->proposals()->update(['status' => ProposalStatus::Closed]);

        return $session;
    }
}
