<?php

namespace App\Actions\Lunch;

use App\Models\LunchDayProposal;
use App\Models\Order;
use InvalidArgumentException;

class CreateOrder
{
    /**
     * @param  array{description: string, price_estimated: float, notes?: string|null}  $data
     */
    public function handle(LunchDayProposal $proposal, string $userId, array $data): Order
    {
        $proposal->loadMissing('lunchDay');

        if (! $proposal->lunchDay->isOpen()) {
            throw new InvalidArgumentException('Lunch day is not open.');
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
