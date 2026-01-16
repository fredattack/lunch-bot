<?php

namespace App\Actions\Lunch;

use App\Models\LunchDayProposal;

class DelegateRole
{
    public function handle(LunchDayProposal $proposal, string $role, string $fromUserId, string $toUserId): bool
    {
        $field = $role === 'runner' ? 'runner_user_id' : 'orderer_user_id';

        if ($proposal->{$field} !== $fromUserId) {
            return false;
        }

        $proposal->{$field} = $toUserId;
        $proposal->save();

        return true;
    }
}
