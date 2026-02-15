<?php

namespace App\Actions\QuickRun;

use App\Enums\QuickRunStatus;
use App\Models\QuickRun;
use InvalidArgumentException;

class LockQuickRun
{
    public function handle(QuickRun $quickRun, ?string $userId = null): QuickRun
    {
        if (! $quickRun->isOpen()) {
            throw new InvalidArgumentException('Ce Quick Run est deja verrouille ou cloture.');
        }

        if ($userId !== null && ! $quickRun->isRunner($userId)) {
            throw new InvalidArgumentException('Seul le runner peut verrouiller ce Quick Run.');
        }

        $quickRun->status = QuickRunStatus::Locked;
        $quickRun->save();

        return $quickRun;
    }
}
