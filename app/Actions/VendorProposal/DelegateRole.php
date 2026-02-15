<?php

namespace App\Actions\VendorProposal;

use App\Models\VendorProposal;
use Illuminate\Support\Facades\DB;

class DelegateRole
{
    public function handle(VendorProposal $proposal, string $role, string $fromUserId, string $toUserId): bool
    {
        $field = $role === 'runner' ? 'runner_user_id' : 'orderer_user_id';

        $success = DB::transaction(function () use ($proposal, $field, $fromUserId, $toUserId): bool {
            $locked = VendorProposal::query()
                ->whereKey($proposal->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || $locked->{$field} !== $fromUserId) {
                return false;
            }

            $locked->{$field} = $toUserId;
            $locked->save();

            return true;
        });

        if ($success) {
            $proposal->refresh();
        }

        return $success;
    }
}
