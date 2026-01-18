<?php

namespace App\Actions\VendorProposal;

use App\Enums\FulfillmentType;
use App\Enums\OrderingMode;
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
        string $createdByUserId,
        OrderingMode $orderingMode = OrderingMode::Individual,
        string $deadlineTime = '11:30'
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

        $runnerUserId = $fulfillment === FulfillmentType::Pickup ? $createdByUserId : null;
        $ordererUserId = $fulfillment === FulfillmentType::Delivery ? $createdByUserId : null;

        return VendorProposal::create([
            'organization_id' => $session->organization_id,
            'lunch_session_id' => $session->id,
            'vendor_id' => $vendor->id,
            'fulfillment_type' => $fulfillment,
            'ordering_mode' => $orderingMode,
            'deadline_time' => $deadlineTime,
            'runner_user_id' => $runnerUserId,
            'orderer_user_id' => $ordererUserId,
            'platform' => $platform,
            'status' => ProposalStatus::Open,
            'created_by_provider_user_id' => $createdByUserId,
        ]);
    }
}
