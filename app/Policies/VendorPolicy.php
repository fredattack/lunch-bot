<?php

namespace App\Policies;

use App\Authorization\Actor;
use App\Models\Vendor;

class VendorPolicy
{
    public function create(Actor $actor): bool
    {
        return true;
    }

    public function update(Actor $actor, Vendor $vendor): bool
    {
        if ($actor->isAdmin) {
            return true;
        }

        return $vendor->created_by_provider_user_id === $actor->providerUserId;
    }

    public function deactivate(Actor $actor, Vendor $vendor): bool
    {
        if ($actor->isAdmin) {
            return true;
        }

        return $vendor->created_by_provider_user_id === $actor->providerUserId;
    }
}
