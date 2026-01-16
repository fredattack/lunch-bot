<?php

namespace Database\Factories;

use App\Enums\FulfillmentType;
use App\Enums\ProposalStatus;
use App\Models\LunchSession;
use App\Models\Vendor;
use App\Models\VendorProposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorProposal>
 */
class VendorProposalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lunch_session_id' => LunchSession::factory(),
            'vendor_id' => Vendor::factory(),
            'fulfillment_type' => fake()->randomElement(FulfillmentType::cases()),
            'runner_user_id' => null,
            'orderer_user_id' => null,
            'platform' => fake()->optional()->word(),
            'status' => ProposalStatus::Open,
            'provider_message_ts' => (string) fake()->unixTime().'.000000',
            'created_by_provider_user_id' => 'U'.fake()->regexify('[A-Z0-9]{10}'),
        ];
    }

    public function withRunner(string $userId): static
    {
        return $this->state(fn () => [
            'runner_user_id' => $userId,
            'status' => ProposalStatus::Ordering,
        ]);
    }

    public function withOrderer(string $userId): static
    {
        return $this->state(fn () => [
            'orderer_user_id' => $userId,
            'status' => ProposalStatus::Ordering,
        ]);
    }

    public function ordering(): static
    {
        return $this->state(fn () => ['status' => ProposalStatus::Ordering]);
    }
}
