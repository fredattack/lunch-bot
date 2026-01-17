<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Organization;
use App\Models\VendorProposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory()->withInstallation(),
            'vendor_proposal_id' => VendorProposal::factory(),
            'provider_user_id' => 'U'.fake()->regexify('[A-Z0-9]{10}'),
            'description' => fake()->sentence(),
            'price_estimated' => fake()->randomFloat(2, 5, 30),
            'price_final' => null,
            'notes' => fake()->optional()->sentence(),
            'audit_log' => [[
                'at' => now()->toIso8601String(),
                'by' => 'U'.fake()->regexify('[A-Z0-9]{10}'),
                'changes' => ['created' => true],
            ]],
        ];
    }

    public function withFinalPrice(float $price): static
    {
        return $this->state(fn () => ['price_final' => $price]);
    }
}
