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
     *     fulfillment_types?: array<string>,
     *     allow_individual_order?: bool
     * } $vendorData
     */
    public function handle(
        LunchSession $session,
        array $vendorData,
        string $proposedByUserId,
        string $deadlineTime = '11:30',
        ?string $note = null,
        bool $helpRequested = false
    ): VendorProposal {
        if (! $session->isOpen()) {
            throw new InvalidArgumentException('La session de lunch est fermee.');
        }

        $fulfillmentTypes = $vendorData['fulfillment_types'] ?? [FulfillmentType::Pickup->value];
        if (empty($fulfillmentTypes)) {
            throw new InvalidArgumentException('Au moins un type de commande doit etre selectionne.');
        }

        return DB::transaction(function () use ($session, $vendorData, $fulfillmentTypes, $proposedByUserId, $deadlineTime, $note, $helpRequested) {
            $vendor = $this->findOrCreateVendor($session, $vendorData, $proposedByUserId);

            $existing = VendorProposal::query()
                ->where('lunch_session_id', $session->id)
                ->where('vendor_id', $vendor->id)
                ->exists();

            if ($existing) {
                throw new InvalidArgumentException('Ce restaurant a deja ete propose pour cette session.');
            }

            $primaryFulfillment = FulfillmentType::from($fulfillmentTypes[0]);
            [$runnerUserId, $ordererUserId] = $this->resolveRoleAssignment($primaryFulfillment, $proposedByUserId);

            return VendorProposal::create([
                'organization_id' => $session->organization_id,
                'lunch_session_id' => $session->id,
                'vendor_id' => $vendor->id,
                'fulfillment_type' => $primaryFulfillment,
                'ordering_mode' => OrderingMode::Shared,
                'deadline_time' => $deadlineTime,
                'help_requested' => $helpRequested,
                'note' => $note,
                'runner_user_id' => $runnerUserId,
                'orderer_user_id' => $ordererUserId,
                'status' => ProposalStatus::Open,
                'created_by_provider_user_id' => $proposedByUserId,
            ]);
        });
    }

    /**
     * @return array{?string, ?string} [runnerUserId, ordererUserId]
     */
    private function resolveRoleAssignment(FulfillmentType $fulfillment, string $proposedByUserId): array
    {
        return match ($fulfillment) {
            FulfillmentType::Pickup => [$proposedByUserId, null],
            FulfillmentType::Delivery => [null, $proposedByUserId],
            FulfillmentType::OnSite => [null, null],
        };
    }

    /**
     * @param array{
     *     name: string,
     *     cuisine_type?: ?string,
     *     url_website?: ?string,
     *     url_menu?: ?string,
     *     fulfillment_types?: array<string>,
     *     allow_individual_order?: bool
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
            'fulfillment_types' => $data['fulfillment_types'] ?? [FulfillmentType::Pickup->value],
            'allow_individual_order' => $data['allow_individual_order'] ?? false,
            'url_website' => $data['url_website'] ?? null,
            'url_menu' => $data['url_menu'] ?? null,
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
     *     fulfillment_types?: array<string>,
     *     allow_individual_order?: bool
     * } $data
     */
    private function updateVendorIfNeeded(Vendor $vendor, array $data): void
    {
        $updates = [];

        if (! empty($data['cuisine_type']) && empty($vendor->cuisine_type)) {
            $updates['cuisine_type'] = $data['cuisine_type'];
        }

        if (! empty($data['fulfillment_types'])) {
            $updates['fulfillment_types'] = $data['fulfillment_types'];
        }

        if (isset($data['allow_individual_order'])) {
            $updates['allow_individual_order'] = $data['allow_individual_order'];
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
