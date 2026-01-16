<?php

namespace App\Actions\Lunch;

use App\Enums\LunchDayStatus;
use App\Models\LunchDayProposal;
use App\Models\Order;
use InvalidArgumentException;

class CreateOrder
{
    public function __construct(
        private readonly UpdateOrder $updateOrder
    ) {}

    /**
     * @param  array{description: string, price_estimated: float, notes?: string|null}  $data
     */
    public function handle(LunchDayProposal $proposal, string $userId, array $data): Order
    {
        $proposal->loadMissing('lunchDay');

        if ($proposal->lunchDay->status !== LunchDayStatus::Open) {
            throw new InvalidArgumentException('Lunch day is not open.');
        }

        $existingOrder = Order::query()
            ->where('lunch_day_proposal_id', $proposal->id)
            ->where('provider_user_id', $userId)
            ->first();

        if ($existingOrder) {
            return $this->updateOrder->handle($existingOrder, $data, $userId);
        }

        return Order::create([
            'lunch_day_proposal_id' => $proposal->id,
            'provider_user_id' => $userId,
            'description' => $data['description'],
            'price_estimated' => $data['price_estimated'],
            'notes' => $data['notes'] ?? null,
            'audit_log' => [[
                'at' => now()->toIso8601String(),
                'by' => $userId,
                'changes' => ['created' => true],
            ]],
        ]);
    }
}
