<?php

namespace App\Actions\Order;

use App\Models\Order;
use InvalidArgumentException;

class DeleteOrder
{
    public function handle(Order $order, string $userId): void
    {
        $order->loadMissing('proposal.lunchSession');

        if ($order->proposal->lunchSession->isClosed()) {
            throw new InvalidArgumentException('Lunch session is closed.');
        }

        if ($order->provider_user_id !== $userId) {
            throw new InvalidArgumentException('You can only delete your own order.');
        }

        $order->delete();
    }
}
