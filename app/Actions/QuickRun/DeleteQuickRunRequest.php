<?php

namespace App\Actions\QuickRun;

use App\Models\QuickRunRequest;
use InvalidArgumentException;

class DeleteQuickRunRequest
{
    public function handle(QuickRunRequest $request, string $userId): void
    {
        $request->loadMissing('quickRun');

        if (! $request->quickRun->isOpen()) {
            throw new InvalidArgumentException('Ce Quick Run n\'accepte plus de modifications.');
        }

        if ($request->provider_user_id !== $userId) {
            throw new InvalidArgumentException('Vous ne pouvez supprimer que vos propres demandes.');
        }

        $request->delete();
    }
}
