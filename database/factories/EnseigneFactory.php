<?php

namespace Database\Factories;

use App\Models\Enseigne;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enseigne>
 */
class EnseigneFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'url_menu' => fake()->url(),
            'notes' => fake()->optional()->sentence(),
            'active' => true,
            'created_by_provider_user_id' => 'U'.fake()->regexify('[A-Z0-9]{10}'),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
