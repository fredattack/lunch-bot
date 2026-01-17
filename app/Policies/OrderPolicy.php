<?php

namespace App\Policies;

use App\Authorization\Actor;
use App\Enums\ProposalStatus;
use App\Models\Order;

class OrderPolicy
{
    public function update(Actor $actor, Order $order): bool
    {
        if ($actor->isAdmin) {
            return true;
        }

        if ($order->provider_user_id !== $actor->providerUserId) {
            return false;
        }

        $proposal = $order->proposal;

        return $proposal && $proposal->status === ProposalStatus::Open;
    }

    public function setFinalPrice(Actor $actor, Order $order): bool
    {
        if ($actor->isAdmin) {
            return true;
        }

        $proposal = $order->proposal;

        if (! $proposal) {
            return false;
        }

        return $proposal->runner_user_id === $actor->providerUserId
            || $proposal->orderer_user_id === $actor->providerUserId;
    }
}
