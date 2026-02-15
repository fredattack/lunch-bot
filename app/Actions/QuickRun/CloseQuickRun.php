<?php

namespace App\Actions\QuickRun;

use App\Enums\QuickRunStatus;
use App\Models\QuickRun;
use InvalidArgumentException;

class CloseQuickRun
{
    /**
     * @param  array<int, array{id: int, price_final: float|null}>  $priceAdjustments
     */
    public function handle(QuickRun $quickRun, string $userId, array $priceAdjustments = []): QuickRun
    {
        if ($quickRun->isClosed()) {
            throw new InvalidArgumentException('Ce Quick Run est deja cloture.');
        }

        if (! $quickRun->isRunner($userId)) {
            throw new InvalidArgumentException('Seul le runner peut cloturer ce Quick Run.');
        }

        foreach ($priceAdjustments as $adjustment) {
            $request = $quickRun->requests()->find($adjustment['id']);
            if ($request && $adjustment['price_final'] !== null) {
                $request->price_final = $adjustment['price_final'];
                $request->save();
            }
        }

        $quickRun->status = QuickRunStatus::Closed;
        $quickRun->save();

        return $quickRun;
    }
}
