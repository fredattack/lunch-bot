<?php

namespace App\Actions\QuickRun;

use App\Models\QuickRun;
use App\Models\QuickRunRequest;
use InvalidArgumentException;

class AddQuickRunRequest
{
    /**
     * @param  array{description: string, price_estimated?: float|null, notes?: string|null}  $data
     */
    public function handle(QuickRun $quickRun, string $userId, array $data): QuickRunRequest
    {
        if (! $quickRun->isOpen()) {
            throw new InvalidArgumentException('Ce Quick Run n\'accepte plus de demandes.');
        }

        if ($quickRun->provider_user_id === $userId) {
            throw new InvalidArgumentException('Le runner ne peut pas ajouter de demande a son propre Quick Run.');
        }

        return QuickRunRequest::create([
            'organization_id' => $quickRun->organization_id,
            'quick_run_id' => $quickRun->id,
            'provider_user_id' => $userId,
            'description' => $data['description'],
            'price_estimated' => $data['price_estimated'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
    }
}
