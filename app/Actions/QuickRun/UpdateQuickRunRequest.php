<?php

namespace App\Actions\QuickRun;

use App\Models\QuickRunRequest;
use InvalidArgumentException;

class UpdateQuickRunRequest
{
    /**
     * @param  array{description?: string, price_estimated?: float|null, notes?: string|null}  $data
     */
    public function handle(QuickRunRequest $request, string $userId, array $data): QuickRunRequest
    {
        $request->loadMissing('quickRun');

        if (! $request->quickRun->isOpen()) {
            throw new InvalidArgumentException('Ce Quick Run n\'accepte plus de modifications.');
        }

        if ($request->provider_user_id !== $userId) {
            throw new InvalidArgumentException('Vous ne pouvez modifier que vos propres demandes.');
        }

        $request->fill($data);
        $request->save();

        return $request;
    }
}
