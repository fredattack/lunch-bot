<?php

namespace App\Actions\Lunch;

use App\Models\Order;
use App\Models\VendorProposal;
use InvalidArgumentException;

class CreateOrder
{
    /**
     * @param  array{description: string, price_estimated: float, notes?: string|null}  $data
     */
    public function handle(VendorProposal $proposal, string $userId, array $data): Order
    {
        $proposal->loadMissing('lunchSession');

        if (! $proposal->lunchSession->isOpen()) {
            throw new InvalidArgumentException('Lunch session is not open.');
        }

        return Order::create([
            'vendor_proposal_id' => $proposal->id,
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
