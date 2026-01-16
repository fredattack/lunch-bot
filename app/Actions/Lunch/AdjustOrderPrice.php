<?php

namespace App\Actions\Lunch;

use App\Models\Order;

class AdjustOrderPrice
{
    public function __construct(
        private readonly UpdateOrder $updateOrder
    ) {}

    public function handle(Order $order, float $priceFinal, string $actorId): Order
    {
        return $this->updateOrder->handle($order, ['price_final' => $priceFinal], $actorId);
    }
}
