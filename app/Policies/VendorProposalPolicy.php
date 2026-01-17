<?php

namespace App\Policies;

use App\Authorization\Actor;
use App\Models\VendorProposal;

class VendorProposalPolicy
{
    public function transferResponsibility(Actor $actor, VendorProposal $proposal): bool
    {
        if ($actor->isAdmin) {
            return true;
        }

        return $this->isResponsible($actor, $proposal);
    }

    public function close(Actor $actor, VendorProposal $proposal): bool
    {
        if ($actor->isAdmin) {
            return true;
        }

        return $this->isResponsible($actor, $proposal);
    }

    private function isResponsible(Actor $actor, VendorProposal $proposal): bool
    {
        $responsibleUserId = $proposal->runner_user_id ?? $proposal->orderer_user_id;

        if ($responsibleUserId === null) {
            return false;
        }

        return $responsibleUserId === $actor->providerUserId;
    }
}
