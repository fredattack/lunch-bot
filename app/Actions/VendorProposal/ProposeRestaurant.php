<?php

namespace App\Actions\VendorProposal;

use App\Enums\FulfillmentType;
use App\Enums\OrderingMode;
use App\Enums\ProposalStatus;
use App\Models\LunchSession;
use App\Models\Vendor;
use App\Models\VendorProposal;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProposeRestaurant
{
    /**
     * @param array{
     *     name: string,
     *     cuisine_type?: ?string,
     *     url_website?: ?string,
     *     url_menu?: ?string,
     *     notes?: ?string
     * } $vendorData
     */
    public function handle(
        LunchSession $session,
        array $vendorData,
        FulfillmentType $fulfillment,
        string $proposedByUserId,
        OrderingMode $orderingMode = OrderingMode::Individual
    ): VendorProposal {
        if (! $session->isOpen()) {
            throw new InvalidArgumentException('La session de lunch est fermee.');
        }

        return DB::transaction(function () use ($session, $vendorData, $fulfillment, $proposedByUserId, $orderingMode) {
            $vendor = $this->findOrCreateVendor($session, $vendorData, $proposedByUserId);

            $existing = VendorProposal::query()
                ->where('lunch_session_id', $session->id)
                ->where('vendor_id', $vendor->id)
                ->exists();

            if ($existing) {
                throw new InvalidArgumentException('Ce restaurant a deja ete propose pour cette session.');
            }

            $runnerUserId = $fulfillment === FulfillmentType::Pickup ? $proposedByUserId : null;
            $ordererUserId = $fulfillment === FulfillmentType::Delivery ? $proposedByUserId : null;

            return VendorProposal::create([
                'organization_id' => $session->organization_id,
                'lunch_session_id' => $session->id,
                'vendor_id' => $vendor->id,
                'fulfillment_type' => $fulfillment,
                'ordering_mode' => $orderingMode,
                'runner_user_id' => $runnerUserId,
                'orderer_user_id' => $ordererUserId,
                'status' => ProposalStatus::Open,
                'created_by_provider_user_id' => $proposedByUserId,
            ]);
        });
    }

    /**
     * @param array{
     *     name: string,
     *     cuisine_type?: ?string,
     *     url_website?: ?string,
     *     url_menu?: ?string,
     *     notes?: ?string
     * } $data
     */
    private function findOrCreateVendor(LunchSession $session, array $data, string $createdByUserId): Vendor
    {
        $vendor = Vendor::query()
            ->where('organization_id', $session->organization_id)
            ->whereRaw('LOWER(name) = LOWER(?)', [$data['name']])
            ->first();

        if ($vendor) {
            $this->updateVendorIfNeeded($vendor, $data);

            return $vendor;
        }

        return Vendor::create([
            'organization_id' => $session->organization_id,
            'name' => $data['name'],
            'cuisine_type' => $data['cuisine_type'] ?? null,
            'url_website' => $data['url_website'] ?? null,
            'url_menu' => $data['url_menu'] ?? null,
            'notes' => $data['notes'] ?? null,
            'active' => true,
            'created_by_provider_user_id' => $createdByUserId,
        ]);
    }

    /**
     * @param array{
     *     name: string,
     *     cuisine_type?: ?string,
     *     url_website?: ?string,
     *     url_menu?: ?string,
     *     notes?: ?string
     * } $data
     */
    private function updateVendorIfNeeded(Vendor $vendor, array $data): void
    {
        $updates = [];

        if (! empty($data['cuisine_type']) && empty($vendor->cuisine_type)) {
            $updates['cuisine_type'] = $data['cuisine_type'];
        }

        if (! empty($data['url_website']) && empty($vendor->url_website)) {
            $updates['url_website'] = $data['url_website'];
        }

        if (! empty($data['url_menu']) && empty($vendor->url_menu)) {
            $updates['url_menu'] = $data['url_menu'];
        }

        if (! empty($updates)) {
            $vendor->update($updates);
        }
    }
}
