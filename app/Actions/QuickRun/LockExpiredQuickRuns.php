<?php

namespace App\Actions\QuickRun;

use App\Enums\QuickRunStatus;
use App\Models\QuickRun;
use Illuminate\Support\Collection;

class LockExpiredQuickRuns
{
    /**
     * @return Collection<int, QuickRun>
     */
    public function handle(): Collection
    {
        $expired = QuickRun::query()
            ->where('status', QuickRunStatus::Open)
            ->where('deadline_at', '<=', now())
            ->get();

        foreach ($expired as $quickRun) {
            $quickRun->status = QuickRunStatus::Locked;
            $quickRun->save();
        }

        return $expired;
    }
}
