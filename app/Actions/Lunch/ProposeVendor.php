<?php

namespace App\Actions\Lunch;

use App\Enums\FulfillmentType;
use App\Enums\ProposalStatus;
use App\Models\LunchSession;
use App\Models\Vendor;
use App\Models\VendorProposal;
use InvalidArgumentException;

class ProposeVendor
{
    public function handle(
        LunchSession $session,
        Vendor $vendor,
        FulfillmentType $fulfillment,
        ?string $platform,
        string $createdByUserId
    ): VendorProposal {
        if (! $session->isOpen()) {
            throw new InvalidArgumentException('Lunch session is not open.');
        }

        $existing = VendorProposal::query()
            ->where('lunch_session_id', $session->id)
            ->where('vendor_id', $vendor->id)
            ->exists();

        if ($existing) {
            throw new InvalidArgumentException('This vendor has already been proposed for this session.');
        }

        return VendorProposal::create([
            'lunch_session_id' => $session->id,
            'vendor_id' => $vendor->id,
            'fulfillment_type' => $fulfillment,
            'platform' => $platform,
            'status' => ProposalStatus::Open,
            'created_by_provider_user_id' => $createdByUserId,
        ]);
    }
}
