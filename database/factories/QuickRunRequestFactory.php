<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\QuickRun;
use App\Models\QuickRunRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuickRunRequest>
 */
class QuickRunRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory()->withInstallation(),
            'quick_run_id' => QuickRun::factory(),
            'provider_user_id' => 'U'.fake()->regexify('[A-Z0-9]{10}'),
            'description' => fake()->sentence(),
            'price_estimated' => fake()->randomFloat(2, 2, 15),
            'price_final' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function withFinalPrice(float $price): static
    {
        return $this->state(fn () => ['price_final' => $price]);
    }
}
