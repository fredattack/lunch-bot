<?php

namespace App\Actions\VendorProposal;

use App\Enums\ProposalStatus;
use App\Models\VendorProposal;
use Illuminate\Support\Facades\DB;

class AssignRole
{
    public function handle(VendorProposal $proposal, string $role, string $userId): bool
    {
        $field = $role === 'runner' ? 'runner_user_id' : 'orderer_user_id';

        $success = DB::transaction(function () use ($proposal, $field, $userId): bool {
            $locked = VendorProposal::query()
                ->whereKey($proposal->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || $locked->{$field}) {
                return false;
            }

            $locked->{$field} = $userId;
            $locked->status = ProposalStatus::Ordering;
            $locked->save();

            return true;
        });

        if ($success) {
            $proposal->refresh();
        }

        return $success;
    }
}
